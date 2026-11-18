<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Itinerary;
use App\Services\Itinerary\BudgetCalculator;
use App\Services\Itinerary\DestinationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ItineraryFixture;

final class ItineraryV2Test extends TestCase
{
    private static int $sequence = 0;

    public function createApplication()
    {
        return ItineraryFixture::application();
    }

    protected function setUp(): void
    {
        parent::setUp();
        ItineraryFixture::schema();
        DB::beginTransaction();
        Http::preventStrayRequests();
        Sanctum::actingAs(Customer::findOrFail(41));
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function payload(array $changes = []): array
    {
        return array_replace([
            'location_id' => 901, 'start_date' => '2030-01-01', 'end_date' => '2030-01-03',
            'adults' => 1, 'children' => [], 'infants' => [], 'stay_mode' => 'own_accommodation',
            'selected_dates' => ['2030-01-01'], 'activity_window_confirmed' => false,
            'estimated_budget' => 1000000, 'contingency_amount' => null,
        ], $changes);
    }

    private function send(string $method, string $path, array $data = [], ?string $key = null)
    {
        return $this->json($method, '/api/v2/itineraries'.$path, $data, ['Idempotency-Key' => $key ?? 'test-'.(++self::$sequence)]);
    }

    private function createTrip(array $changes = []): array
    {
        return $this->send('POST', '', $this->payload($changes))->assertCreated()->json('data');
    }

    public static function invalidTrips(): array
    {
        return [
            'nine adults' => [['adults' => 9], 'adults'],
            'nine including infant' => [['adults' => 8, 'infants' => [['age' => 8, 'unit' => 'months']]], 'adults'],
            'no adult' => [['adults' => 0, 'children' => [['age' => 5, 'unit' => 'years']]], 'adults'],
            'fractional adults' => [['adults' => 1.5], 'adults'],
            'boolean adults' => [['adults' => true], 'adults'],
            'string adults' => [['adults' => '2'], 'adults'],
            'missing child age' => [['children' => [['unit' => 'years']]], 'children.0.age'],
            'missing infant unit' => [['infants' => [['age' => 4]]], 'infants.0.unit'],
            'fractional age' => [['children' => [['age' => 1.2, 'unit' => 'years']]], 'children.0.age'],
            '31 calendar days' => [['end_date' => '2030-01-31'], 'end_date'],
            'date outside trip' => [['selected_dates' => ['2030-01-04']], 'selected_dates.0'],
            'duplicate dates' => [['selected_dates' => ['2030-01-01', '2030-01-01']], 'selected_dates.0'],
            'six selected dates' => [['end_date' => '2030-01-10', 'selected_dates' => ['2030-01-01', '2030-01-02', '2030-01-03', '2030-01-04', '2030-01-05', '2030-01-06']], 'selected_dates'],
            'no selected dates' => [['selected_dates' => []], 'selected_dates'],
            'day trip no arrival' => [['stay_mode' => 'day_trip', 'end_date' => '2030-01-01', 'departure_time' => '18:00'], 'arrival_time'],
            'day trip reversed hours' => [['stay_mode' => 'day_trip', 'end_date' => '2030-01-01', 'arrival_time' => '18:00', 'departure_time' => '08:00'], 'departure_time'],
            'day trip multiple days' => [['stay_mode' => 'day_trip', 'arrival_time' => '08:00', 'departure_time' => '18:00'], 'end_date'],
            'day trip hotel' => [['stay_mode' => 'day_trip', 'end_date' => '2030-01-01', 'arrival_time' => '08:00', 'departure_time' => '18:00', 'hotel_id' => 301], 'hotel_id'],
            'unknown location' => [['location_id' => 99999], 'location_id'],
            'location name as id' => [['location_id' => 'Hồ Chí Minh'], 'location_id'],
            'malformed location with hotel' => [['location_id' => [901], 'stay_mode' => 'aurel_hotel', 'hotel_id' => 301,
                'lodging_check_in' => '2030-01-01', 'lodging_check_out' => '2030-01-03'], 'location_id'],
            'zero budget' => [['estimated_budget' => 0], 'estimated_budget'],
            'fractional budget' => [['estimated_budget' => 1.5], 'estimated_budget'],
            'null budget' => [['estimated_budget' => null], 'estimated_budget'],
            'storage overflow budget' => [['estimated_budget' => 10000000000], 'estimated_budget'],
            'negative contingency' => [['contingency_amount' => -1], 'contingency_amount'],
            'allowance outside trip' => [['free_day_allowances' => [['date' => '2030-01-05', 'amount' => 0]]], 'free_day_allowances.0.date'],
            'unprovided confirmed window' => [['activity_window_confirmed' => true], 'activity_window_start'],
            'reversed daily window' => [['activity_window_start' => '18:00', 'activity_window_end' => '08:00'], 'activity_window_end'],
            'hotel in another destination' => [['stay_mode' => 'aurel_hotel', 'lodging_check_in' => '2030-01-01', 'lodging_check_out' => '2030-01-03', 'hotel_id' => 302], 'hotel_id'],
            'zero rental nights' => [['stay_mode' => 'aurel_hotel', 'lodging_check_in' => '2030-01-02', 'lodging_check_out' => '2030-01-02'], 'lodging_check_out'],
            'rental outside trip' => [['stay_mode' => 'aurel_hotel', 'lodging_check_in' => '2029-12-31', 'lodging_check_out' => '2030-01-03'], 'lodging_check_in'],
            'client customer id' => [['customer_id' => 42], 'customer_id'],
            'client fake quote' => [['costs' => [['unit_amount' => 0]]], 'costs'],
            'client fake room plan' => [['room_options' => [['quantity' => 1]]], 'room_options'],
        ];
    }

    #[DataProvider('invalidTrips')]
    public function test_invalid_trip_input_never_creates_rows(array $changes, string $error): void
    {
        $this->send('POST', '', $this->payload($changes))->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->assertSame(1, DB::table('itinerary')->count());
        $this->assertSame(0, DB::table('itinerary_write_request')->count());
    }

    public function test_supported_destination_search_and_no_free_text_creation(): void
    {
        $this->send('GET', '/destinations?q='.urlencode('Sài Gòn'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 901)
            ->assertJsonPath('data.0.name', 'Hồ Chí Minh')->assertJsonPath('data.0.provider_region_verified', false);
        DB::table('location')->insert(['id' => 999, 'name' => 'Unsupported synthetic location']);
        $this->send('POST', '', $this->payload(['location_id' => 999]))->assertUnprocessable()->assertJsonValidationErrors('location_id');
        $payload = $this->payload();
        unset($payload['location_id']);
        $payload['destination'] = 'Nghệ An';
        $this->send('POST', '', $payload)->assertUnprocessable()->assertJsonValidationErrors(['location_id', 'destination']);
        $this->assertSame(3, DB::table('location')->count());
        DB::table('itinerary_destination')->where('location_id', 901)->update(['enabled' => false]);
        $this->send('POST', '', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('location_id');
    }

    public function test_one_and_eight_guests_and_unverified_ages_are_preserved_without_thresholds(): void
    {
        $one = $this->createTrip();
        $this->assertSame(1, $one['party']['total']);
        $eight = $this->createTrip(['adults' => 6, 'children' => [['age' => 15, 'unit' => 'years']], 'infants' => [['age' => 36, 'unit' => 'months']]]);
        $this->assertSame(8, $eight['party']['total']);
        $this->assertFalse($eight['party']['age_policy_verified']);
        $this->assertSame(36, $eight['party']['infants'][0]['age']);
    }

    public function test_one_day_trip_and_30_calendar_days_are_not_30_nights(): void
    {
        $one = $this->createTrip(['stay_mode' => 'day_trip', 'end_date' => '2030-01-01', 'arrival_time' => '08:00', 'departure_time' => '18:00']);
        $this->assertSame(1, $one['total_days']);
        $this->assertSame(0, $one['total_nights']);
        $this->assertSame('08:00', $one['activity_window']['start']);
        $this->assertSame([], $one['lodging']['uncovered_nights']);
        $long = $this->createTrip(['stay_mode' => 'aurel_hotel', 'end_date' => '2030-01-30', 'hotel_id' => 301,
            'lodging_check_in' => '2030-01-01', 'lodging_check_out' => '2030-01-30',
            'selected_dates' => ['2030-01-01', '2030-01-04', '2030-01-12', '2030-01-20', '2030-01-30']]);
        $this->assertSame(30, $long['total_days']);
        $this->assertSame(29, $long['total_nights']);
        $this->assertCount(29, $long['lodging']['uncovered_nights']);
        $this->assertCount(30, $long['days']);
        $this->assertSame(['free'], array_values(array_unique(array_column($long['days'], 'planning_status'))));
        $this->assertFalse($long['lodging']['reservation_created']);
        $this->assertSame(0, DB::table('itinerary_lodging_room')->count());
    }

    public function test_unknown_hours_remain_unknown_and_explicit_window_can_be_confirmed(): void
    {
        $draft = $this->createTrip();
        $this->assertNull($draft['arrival_time']);
        $this->assertNull($draft['activity_window']['start']);
        $this->assertFalse($draft['activity_window']['confirmed']);
        $confirmed = $this->createTrip(['activity_window_start' => '09:00', 'activity_window_end' => '17:00', 'activity_window_confirmed' => true]);
        $this->assertTrue($confirmed['activity_window']['confirmed']);
        $this->assertFalse($confirmed['readiness']['generation_available']);
    }

    public function test_null_zero_missing_prices_and_budget_overrun(): void
    {
        $trip = $this->createTrip(['contingency_amount' => 0, 'free_day_allowances' => [['date' => '2030-01-02', 'amount' => 0]]]);
        $this->assertNull($trip['days'][0]['free_day_allowance']);
        $this->assertSame(0, $trip['days'][1]['free_day_allowance']);
        $this->assertSame('incomplete', $trip['budget']['status']);
        $this->assertFalse($trip['budget']['complete']);
        $this->cost($trip['id'], ['unit_amount' => null, 'price_status' => 'unknown']);
        $this->send('GET', '/'.$trip['id'])->assertOk()->assertJsonPath('data.budget.status', 'incomplete');
        $this->cost($trip['id'], ['unit_amount' => 1000001, 'price_status' => 'known']);
        $this->send('GET', '/'.$trip['id'])->assertOk()->assertJsonPath('data.budget.status', 'over_budget')->assertJsonPath('data.budget.complete', false);
    }

    public function test_planned_day_replaces_allowance_and_never_counts_it_twice(): void
    {
        $trip = $this->createTrip(['contingency_amount' => 0, 'free_day_allowances' => [
            ['date' => '2030-01-01', 'amount' => 100], ['date' => '2030-01-02', 'amount' => 0], ['date' => '2030-01-03', 'amount' => 0],
        ]]);
        $day = DB::table('itinerary_calendar_day')->where('itinerary_id', $trip['id'])->where('date', '2030-01-01')->first();
        DB::table('itinerary_calendar_day')->where('id', $day->id)->update(['planning_status' => 'planned']);
        $this->cost($trip['id'], ['calendar_day_id' => $day->id, 'unit_amount' => 30, 'quantity' => 2, 'price_status' => 'known']);
        $budget = app(BudgetCalculator::class)->forTrip(Itinerary::findOrFail($trip['id']));
        $this->assertSame(60, $budget['accounted_total']);
        $this->assertSame(0, $budget['categories']['free_days']['known_amount']);
        $this->assertSame('within_budget', $budget['status']);
        $this->assertFalse($budget['feasibility_checked']);
        DB::table('itinerary_cost_line')->where('itinerary_id', $trip['id'])->update(['price_status' => 'estimated']);
        $this->assertSame('estimated_within_budget', app(BudgetCalculator::class)->forTrip(Itinerary::findOrFail($trip['id']))['status']);
    }

    public function test_lodging_is_excluded_in_own_accommodation_mode_and_overflow_is_not_a_float(): void
    {
        $trip = $this->createTrip();
        $this->cost($trip['id'], ['category' => 'lodging', 'unit_amount' => 9999999, 'price_status' => 'known']);
        $budget = app(BudgetCalculator::class)->forTrip(Itinerary::findOrFail($trip['id']));
        $this->assertSame(0, $budget['categories']['lodging']['known_amount']);
        $this->cost($trip['id'], ['unit_amount' => 9999999999, 'quantity' => 4294967295, 'price_status' => 'known']);
        $budget = app(BudgetCalculator::class)->forTrip(Itinerary::findOrFail($trip['id']));
        $this->assertSame('over_budget', $budget['status']);
        $this->assertTrue($budget['calculation_overflow']);
        $this->assertNull($budget['accounted_total']);
    }

    public function test_owned_crud_status_and_revision_conflict(): void
    {
        $trip = $this->createTrip();
        $id = $trip['id'];
        $this->send('GET', '')->assertOk()->assertJsonCount(1, 'data');
        $this->send('PUT', '/'.$id, $this->payload(['title' => 'Updated trip', 'expected_revision' => 1]))->assertOk()->assertJsonPath('data.revision', 2);
        $this->send('PATCH', '/'.$id, ['status' => 'saved', 'expected_revision' => 1])->assertConflict();
        $this->send('PATCH', '/'.$id, ['status' => 'saved', 'expected_revision' => 2])->assertOk()->assertJsonPath('data.revision', 3)
            ->assertJsonPath('data.status', 'saved')->assertJsonPath('data.readiness.validation_status', 'not_checked')->assertJsonPath('data.budget.status', 'incomplete');
        $this->send('GET', '?status=saved')->assertOk()->assertJsonCount(1, 'data');
        $this->send('PUT', '/'.$id, $this->payload(['expected_revision' => 3]))->assertConflict();
        $this->send('PATCH', '/'.$id, ['status' => 'draft', 'expected_revision' => 3])->assertOk()->assertJsonPath('data.revision', 4);
        $this->send('DELETE', '/'.$id, ['expected_revision' => 4], 'delete-owned')->assertNoContent();
        $this->send('DELETE', '/'.$id, ['expected_revision' => 4], 'delete-owned')->assertNoContent()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame(0, DB::table('itinerary_calendar_day')->where('itinerary_id', $id)->count());
        $this->assertDatabaseHas('itinerary', ['id' => 1, 'title' => 'Legacy preserved']);
    }

    public function test_cross_customer_access_and_anonymous_access_are_refused(): void
    {
        $trip = $this->createTrip();
        $id = $trip['id'];
        Sanctum::actingAs(Customer::findOrFail(42));
        $this->send('GET', '/'.$id)->assertNotFound();
        $this->send('PUT', '/'.$id, $this->payload(['expected_revision' => 1]))->assertNotFound();
        $this->send('PATCH', '/'.$id, ['status' => 'saved', 'expected_revision' => 1])->assertNotFound();
        $this->send('DELETE', '/'.$id, ['expected_revision' => 1])->assertNotFound();
        $this->send('GET', '')->assertOk()->assertJsonCount(0, 'data');
        $this->app['auth']->forgetGuards();
        $this->send('GET', '')->assertUnauthorized();
    }

    public function test_idempotent_creation_and_status_do_not_write_twice(): void
    {
        $first = $this->send('POST', '', $this->payload(), 'same-create')->assertCreated()->json('data');
        $this->send('POST', '', $this->payload(), 'same-create')->assertCreated()->assertHeader('Idempotency-Replayed', 'true')->assertJsonPath('data.id', $first['id']);
        $this->send('POST', '', $this->payload(['adults' => 2]), 'same-create')->assertConflict();
        $this->assertSame(2, DB::table('itinerary')->count());
        $body = ['status' => 'saved', 'expected_revision' => 1];
        $this->send('PATCH', '/'.$first['id'], $body, 'same-status')->assertOk()->assertJsonPath('data.revision', 2);
        $this->send('PATCH', '/'.$first['id'], $body, 'same-status')->assertOk()->assertJsonPath('data.revision', 2);
        $this->assertSame(2, Itinerary::findOrFail($first['id'])->revision);
        Sanctum::actingAs(Customer::findOrFail(42));
        $this->send('POST', '', $this->payload(), 'same-create')->assertCreated();
    }

    public function test_non_customer_identity_and_admin_session_cannot_use_customer_trip_api(): void
    {
        Sanctum::actingAs(new \App\Models\User);
        $this->send('GET', '')->assertForbidden();
        $customer = Customer::findOrFail(41);
        $customer->role = 'admin';
        Sanctum::actingAs($customer);
        $customer->withAccessToken(new \Laravel\Sanctum\PersonalAccessToken(['name' => 'admin_token', 'abilities' => ['admin']]));
        $this->send('GET', '/destinations')->assertForbidden();
    }

    public function test_request_keys_are_case_sensitive_and_required_in_the_header(): void
    {
        $this->postJson('/api/v2/itineraries', $this->payload(['request_key' => 'body-is-not-a-header']))->assertUnprocessable()->assertJsonValidationErrors('request_key');
        $upper = $this->send('POST', '', $this->payload(), 'Case-Key')->assertCreated()->json('data.id');
        $lower = $this->send('POST', '', $this->payload(), 'case-key')->assertCreated()->json('data.id');
        $this->assertNotSame($upper, $lower);
    }

    public function test_legacy_reads_survive_and_all_five_legacy_mutations_are_blocked(): void
    {
        $this->getJson('/api/itineraries/1')->assertOk()->assertJsonPath('title', 'Legacy preserved');
        foreach ([['PUT', '/1', ['title' => 'Forbidden']], ['DELETE', '/1', []], ['PUT', '/1/items/1', ['title' => 'Forbidden']],
            ['DELETE', '/1/items/1', []], ['POST', '/1/chat', ['message' => 'Change it']]] as [$method, $path, $data]) {
            $this->json($method, '/api/itineraries'.$path, $data)->assertConflict()->assertJsonPath('code', 'legacy_itinerary_read_only');
        }
        $this->assertDatabaseHas('itinerary_item', ['id' => 1, 'title' => 'Legacy item preserved']);
        $this->send('GET', '/1')->assertNotFound();
        $trip = $this->createTrip();
        $this->putJson('/api/itineraries/'.$trip['id'], ['title' => 'Bypass'])->assertConflict()->assertJsonPath('code', 'use_itinerary_v2_api');
        Sanctum::actingAs(Customer::findOrFail(42));
        $this->deleteJson('/api/itineraries/1')->assertNotFound();
    }

    public function test_region_mapping_requires_verified_administrative_evidence(): void
    {
        $this->assertSame(0, DB::table('itinerary_region_mapping')->count());
        DB::table('itinerary_region_mapping')->insert(['location_id' => 901, 'provider' => 'geoapify', 'provider_region_id' => 'external-region-id',
            'verification_status' => 'verified', 'verified_at' => now(), 'verification_evidence' => json_encode(['method' => 'radius', 'source_reference' => 'synthetic'])]);
        $this->assertFalse(app(DestinationService::class)->verifiedRegion(901));
        DB::table('itinerary_region_mapping')->where('location_id', 901)->update(['verification_evidence' => json_encode(['method' => 'administrative_region', 'source_reference' => 'synthetic-provider-proof'])]);
        $this->assertTrue(app(DestinationService::class)->verifiedRegion(901));
        $this->assertFalse(app(DestinationService::class)->verifiedRegion(72));
        $trip = $this->createTrip();
        $this->assertFalse($trip['readiness']['activities_generated']);
        $this->assertSame(0, DB::table('itinerary_place_snapshot')->count());
    }

    public function test_transaction_rolls_back_a_failure_after_trip_insertion(): void
    {
        $fail = true;
        DB::listen(function ($event) use (&$fail) {
            if ($fail && str_contains($event->sql, 'insert into `itinerary_calendar_day`')) {
                $fail = false;
                throw new \RuntimeException('Synthetic write interruption');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->send('POST', '', $this->payload(), 'interrupted');
            $this->fail('Expected interruption');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic write interruption', $e->getMessage());
        }
        $this->assertSame(1, DB::table('itinerary')->count());
        $this->assertSame(0, DB::table('itinerary_calendar_day')->count());
        $this->assertSame(0, DB::table('itinerary_write_request')->count());
        $this->send('POST', '', $this->payload(), 'interrupted')->assertCreated();
    }

    public function test_mysql_migration_types_unique_dates_foreign_keys_and_legacy_preservation(): void
    {
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertSame(['mysql'], array_keys(config('database.connections')));
        $this->assertDatabaseHas('itinerary', ['id' => 1, 'schema_version' => 1, 'revision' => 0, 'title' => 'Legacy preserved']);
        $columns = collect(DB::select("SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='itinerary_cost_line'"))->pluck('COLUMN_TYPE', 'COLUMN_NAME');
        $this->assertSame('int', $columns['itinerary_id']);
        $this->assertSame('int', $columns['item_id']);
        $this->assertSame('bigint unsigned', $columns['calendar_day_id']);
        $trip = $this->createTrip();
        $invalid = [
            ['itinerary_calendar_day', ['itinerary_id' => 999999, 'date' => '2030-01-01']],
            ['itinerary_lodging_room', ['itinerary_id' => $trip['id'], 'night' => '2030-01-01', 'room_type_id' => 999999, 'quantity' => 1]],
            ['itinerary_place_snapshot', ['itinerary_id' => $trip['id'], 'attraction_id' => 999999, 'provider' => 'synthetic', 'provider_place_id' => 'synthetic', 'snapshot' => '{}']],
        ];
        foreach ($invalid as [$table, $row]) {
            try {
                DB::table($table)->insert($row);
                $this->fail('Expected FK enforcement');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->getCode());
                $this->assertSame(1452, $e->errorInfo[1]);
            }
        }
        try {
            DB::table('itinerary_calendar_day')->insert(['itinerary_id' => $trip['id'], 'date' => '2030-01-01']);
            $this->fail('Expected unique date');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
        }
        foreach (['Opaque-Place-ID', 'opaque-place-id'] as $sourceId) {
            DB::table('itinerary_place_snapshot')->insert(['itinerary_id' => $trip['id'], 'provider' => 'synthetic', 'provider_place_id' => $sourceId, 'snapshot' => '{}']);
        }
        $this->assertSame(2, DB::table('itinerary_place_snapshot')->where('itinerary_id', $trip['id'])->count());
    }

    private function cost(int $tripId, array $changes): void
    {
        DB::table('itinerary_cost_line')->insert(array_replace(['itinerary_id' => $tripId, 'category' => 'activity', 'price_status' => 'unknown',
            'unit' => 'group', 'currency' => 'VND', 'unit_amount' => null, 'quantity' => 1, 'source' => 'synthetic-test-only'], $changes));
    }
}
