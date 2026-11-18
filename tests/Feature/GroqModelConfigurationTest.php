<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Customer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ItineraryFixture;

final class GroqModelConfigurationTest extends TestCase
{
    public function createApplication() { return ItineraryFixture::application(); }

    protected function setUp(): void
    {
        parent::setUp();
        ItineraryFixture::schema();
        // Only the guarded, runner-owned MySQL fixture is extended for Chat.
        foreach (['chat_session', 'chat_message'] as $table) {
            if (! Schema::hasTable($table)) {
                (require base_path('database/migrations/2026_04_02_013436_create_'.$table.'_table.php'))->up();
            }
        }
        foreach (['star_rating', 'avg_rating'] as $column) {
            if (! Schema::hasColumn('hotel', $column)) {
                Schema::table('hotel', fn (Blueprint $t) => $t->decimal($column, 4, 2)->nullable());
            }
        }
        if (! Schema::hasColumn('hotel', 'address')) {
            Schema::table('hotel', fn (Blueprint $t) => $t->string('address')->nullable());
        }
        DB::beginTransaction();
        Cache::flush();
        Http::preventStrayRequests();
        Sanctum::actingAs(Customer::findOrFail(41));
        config(['services.groq.api_key' => 'isolated-secret-never-expose', 'services.geoapify.api_key' => 'isolated-map-key']);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) DB::rollBack();
        parent::tearDown();
    }

    private function startChatSession(): int
    {
        return $this->postJson('/api/chat/start')->assertCreated()->json('session_id');
    }

    private function generate()
    {
        return $this->postJson('/api/v2/itineraries/generate', [
            'location_id' => 901, 'start_date' => '2030-01-01', 'end_date' => '2030-01-01',
            'adults' => 2, 'children' => [], 'infants' => [], 'stay_mode' => 'own_accommodation',
            'selected_dates' => ['2030-01-01'], 'activity_window_start' => '08:00', 'activity_window_end' => '20:00',
            'activity_window_confirmed' => true, 'estimated_budget' => 1000000, 'transport' => 'walk',
        ], ['Idempotency-Key' => 'groq-shared-config-test']);
    }

    private function providers(array $outcome = []): void
    {
        Http::fake(function ($request) use ($outcome) {
            if (str_contains($request->url(), '/v2/place-details')) return Http::response(['features'=>[['properties'=>['place_id'=>'region-config-audit'],'geometry'=>['type'=>'Polygon','coordinates'=>[[[106,10],[107,10],[107,11],[106,11],[106,10]]]]]]]);
            if (str_contains($request->url(), '/geocode/')) {
                return Http::response(['features' => [['properties' => ['place_id' => 'region-config-audit', 'country_code' => 'vn',
                    'result_type' => 'city', 'category' => 'administrative', 'name' => 'Hồ Chí Minh', 'lon' => 106.7, 'lat' => 10.77]]]]);
            }
            if (str_contains($request->url(), '/v2/places')) {
                return Http::response(['features' => array_map(fn ($i) => ['properties' => ['place_id' => 'audit-place-'.$i,
                    'name' => 'Synthetic audit place '.$i, 'country_code' => 'vn', 'categories' => ['tourism.sights']],
                    'geometry' => ['coordinates' => [106.7 + $i / 1000, 10.77]]], [0, 1])]);
            }
            if (str_contains($request->url(), '/routematrix')) {
                return Http::response(['sources_to_targets' => [[['time' => 0, 'distance' => 0], ['time' => 60, 'distance' => 100]],
                    [['time' => 60, 'distance' => 100], ['time' => 0, 'distance' => 0]]]]);
            }
            if ($request->url() !== 'https://api.groq.com/openai/v1/chat/completions') {
                throw new \RuntimeException('Unexpected HTTP request in isolated test.');
            }
            if ($outcome['timeout'] ?? false) throw new ConnectionException('isolated-secret-never-expose');
            if (isset($outcome['status'])) {
                return Http::response(['error' => ['code' => $outcome['error'] ?? 'provider_failure',
                    'message' => 'isolated-secret-never-expose']], $outcome['status']);
            }
            $content = 'Bạn có thể dành hai ngày khám phá thành phố.';
            if (str_contains($request['messages'][0]['content'], 'AUREL:UNDERSTAND')) {
                $content=json_encode(['intent'=>'preferences','patch'=>['duration_days'=>2],'question'=>null]);
                if (array_key_exists('content', $outcome)) $content=$outcome['content'];
            } elseif (str_contains($request['messages'][0]['content'], 'AUREL:ADVISE')) {
                $content=json_encode(['message'=>$content,'place_ids'=>[],'hotel_ids'=>[],'location_ids'=>[],'question'=>null]);
            } elseif (isset($request['response_format'])) {
                $content = json_encode(['is_valid' => true, 'title' => 'Synthetic config audit', 'days' => [['date' => '2030-01-01',
                    'items' => [['candidate_id' => 0, 'duration_minutes' => 60, 'description' => 'Gợi ý tham quan'],
                        ['candidate_id' => 1, 'duration_minutes' => 60, 'description' => 'Điểm tiếp theo']]]]], JSON_THROW_ON_ERROR);
            } elseif (array_key_exists('content', $outcome)) {
                $content = $outcome['content'];
            }
            return Http::response(['choices' => [['message' => ['content' => $content]]]]);
        });
    }

    public static function modelConfigurations(): array
    {
        return [['openai/gpt-oss-20b'], ['isolated-model-from-config']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('modelConfigurations')]
    public function test_chat_and_generation_send_the_shared_configured_model(string $model): void
    {
        config(['services.groq.model' => $model]);
        $this->providers();$id = $this->startChatSession();
        ChatMessage::create(['session_id' => $id, 'role' => 'user', 'content' => 'Tôi muốn đến thành phố Hồ Chí Minh.', 'created_at' => now()->subMinutes(2)]);
        ChatMessage::create(['session_id' => $id, 'role' => 'bot', 'content' => 'Bạn dự định đi mấy ngày?', 'created_at' => now()->subMinute()]);
        $this->postJson('/api/chat/'.$id.'/message', ['message' => 'Hai ngày nhé.'])->assertOk()
            ->assertJsonPath('message', 'Bạn có thể dành hai ngày khám phá thành phố.');
        $this->generate()->assertCreated();
        $requests = Http::recorded(fn ($r) => str_contains($r->url(), 'api.groq.com/'))->pluck(0);
        $this->assertCount(3, $requests);
        foreach ($requests as $request) $this->assertSame($model, $request['model']);
        $this->assertSame(['system', 'user', 'assistant', 'user'], array_column($requests[0]['messages'], 'role'));
        $this->assertSame(0.3, $requests[0]['temperature']);$this->assertSame(1800, $requests[0]['max_tokens']);
        $this->assertSame(0.2, $requests[2]['temperature']);$this->assertSame(4000, $requests[2]['max_tokens']);
        $this->assertSame(['type' => 'json_object'], $requests[2]['response_format']);
        $this->assertSame(2, ChatMessage::where('session_id', $id)->where('role', 'bot')->count());
    }

    public function test_workflow_parameters_can_be_configured_independently(): void
    {
        config(['services.groq.chat.temperature' => 0.55, 'services.groq.chat.max_tokens' => 650,
            'services.groq.itinerary.temperature' => 0.15, 'services.groq.itinerary.max_tokens' => 4500]);
        $this->providers();$id = $this->startChatSession();
        $this->postJson('/api/chat/'.$id.'/message', ['message' => 'Xin tư vấn.'])->assertOk();
        $this->generate()->assertCreated();
        $requests = Http::recorded(fn ($r) => str_contains($r->url(), 'api.groq.com/'))->pluck(0);
        $this->assertSame(0.55, $requests[0]['temperature']);$this->assertSame(650, $requests[0]['max_tokens']);
        $this->assertSame(0.15, $requests[2]['temperature']);$this->assertSame(4500, $requests[2]['max_tokens']);
    }

    public static function unavailableProviders(): array
    {
        return [
            [429, null, 'groq_quota', 'groq_quota', 'giới hạn', 'hạn mức'],
            [400, 'model_decommissioned', 'groq_model_unavailable', 'groq_model_decommissioned', 'Mô hình', 'Mô hình'],
            [404, 'model_not_found', 'groq_model_unavailable', 'groq_model_not_found', 'Mô hình', 'Mô hình'],
            [404, null, 'groq_model_unavailable', 'groq_model_unavailable', 'Mô hình', 'Mô hình'],
            [401, null, 'groq_access_denied', 'groq_access_denied', 'chưa sẵn sàng', 'chưa sẵn sàng'],
            [403, null, 'groq_access_denied', 'groq_access_denied', 'chưa sẵn sàng', 'chưa sẵn sàng'],
            [503, null, 'groq_unavailable', 'groq_unavailable', 'chưa sẵn sàng', 'chưa sẵn sàng'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unavailableProviders')]
    public function test_provider_failures_are_friendly_and_do_not_store_bot_replies_or_partial_trips(
        int $status, ?string $error, string $chatCode, string $tripCode, string $chatMessage, string $tripMessage
    ): void {
        $this->providers(['status' => $status, 'error' => $error]);$id = $this->startChatSession();$before = DB::table('itinerary')->count();
        $chat = $this->postJson('/api/chat/'.$id.'/message', ['message' => 'Xin tư vấn.'])->assertOk()->assertJsonPath('code', $chatCode);
        $trip = $this->generate()->assertStatus(503)->assertJsonPath('code', $tripCode)->assertJsonPath('provider_status', $status);
        $this->assertStringContainsString($chatMessage, $chat->json('message'));
        $this->assertStringContainsString($tripMessage, $trip->json('message'));
        foreach ([$chat, $trip] as $response) $this->assertStringNotContainsString('isolated-secret-never-expose', $response->getContent());
        $this->assertSame(0, ChatMessage::where('session_id', $id)->where('role', 'bot')->count());
        $this->assertSame($before, DB::table('itinerary')->count());
        $this->assertCount(2, Http::recorded(fn ($r) => str_contains($r->url(), 'api.groq.com/')));
    }

    public function test_timeout_is_caught_in_both_workflows_without_exposing_exception_text(): void
    {
        $this->providers(['timeout' => true]);$id = $this->startChatSession();
        $chat = $this->postJson('/api/chat/'.$id.'/message', ['message' => 'Xin tư vấn.'])->assertOk()->assertJsonPath('code', 'groq_unreachable');
        $trip = $this->generate()->assertStatus(503)->assertJsonPath('code', 'groq_unreachable');
        foreach ([$chat, $trip] as $response) $this->assertStringNotContainsString('isolated-secret-never-expose', $response->getContent());
        $this->assertSame(0, ChatMessage::where('session_id', $id)->where('role', 'bot')->count());
    }

    public static function emptyReplies(): array { return [[null], [''], ['   '], [[]]]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('emptyReplies')]
    public function test_chat_rejects_missing_or_invalid_content_without_storing_a_bot_message(mixed $content): void
    {
        $this->providers(['content' => $content]);$id = $this->startChatSession();
        $this->postJson('/api/chat/'.$id.'/message', ['message' => 'Xin tư vấn.'])->assertOk()->assertJsonPath('code', 'groq_invalid_response');
        $this->assertSame(0, ChatMessage::where('session_id', $id)->where('role', 'bot')->count());
    }

    public function test_chat_logs_only_the_safe_error_status_and_code(): void
    {
        Log::spy();$this->providers(['status' => 400, 'error' => 'model_decommissioned']);$id = $this->startChatSession();
        $this->postJson('/api/chat/'.$id.'/message', ['message' => 'Xin tư vấn.'])->assertOk();
        Log::shouldHaveReceived('warning')->once()->with('Groq chat request failed', ['status' => 400, 'code' => 'groq_model_unavailable']);
    }

    public function test_guarded_legacy_chat_stays_blocked_before_the_old_model_is_called(): void
    {
        $this->postJson('/api/itineraries/1/chat', ['message' => 'Đổi lịch giúp tôi.'])->assertConflict()
            ->assertJsonPath('code', 'legacy_itinerary_read_only');
        Http::assertNothingSent();
    }
}
