<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerAccountUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Http::preventStrayRequests();
        Mail::fake();
        config(['app.url' => 'http://127.0.0.1', 'roomtype-v2.final_integration' => false]);
        app('url')->forceRootUrl('http://127.0.0.1');
    }

    public static function pages(): array
    {
        return [['profile','Thông tin cá nhân'], ['bookings','Lịch sử đặt phòng'],
            ['itineraries','Lịch trình AI'], ['password','Bảo mật tài khoản'], ['settings','Cài đặt']];
    }

    #[DataProvider('pages')]
    public function test_shared_account_pages_render_without_database_or_external_requests(string $page, string $title): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $response = $this->get('/'.$page)->assertOk()
            ->assertSee('css/aurel-account.css', false)->assertSee('fonts/admin/be-vietnam-pro.css', false)
            ->assertDontSee('cdnjs.cloudflare', false)->assertDontSee('fonts.googleapis.com', false);
        if ($page === 'itineraries') {
            $response->assertSee('Chuyến đi tiếp theo của bạn?')->assertSee('id="aurelAi"', false)
                ->assertSee('Chọn điểm đến, cân đối ngân sách và sắp xếp hành trình của bạn.')->assertDontSee('id="itineraryForm"', false)
                ->assertDontSee('class="ai-demo-badge"', false)->assertDontSee('class="ai-wordmark"', false)
                ->assertDontSee('/api/v2/itineraries/generate', false)
                ->assertDontSee('id="sidebar"', false)->assertDontSee('id="accountMenuToggle"', false)
                ->assertDontSee("window.location.href = '/login'", false)->assertDontSee('/notifications', false);
        } else {
            $response->assertSee('<h1>'.$title.'</h1>', false)->assertDontSee('css/aurel-ai-chat.css', false)
                ->assertSee('id="accountMenuToggle"', false)->assertSee('aria-modal="true"', false);
        }
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//nav[contains(@class,"sidebar-nav")]/a');
        if ($page === 'itineraries') {
            $this->assertCount(0, $links, 'AI has its own page, outside the account navigation shell.');
        } else {
            $this->assertSame(['/profile','/bookings','/itineraries','/password','/settings'], array_map(fn($link) => $link->getAttribute('href'), iterator_to_array($links)));
            $this->assertCount(1, $xpath->query('//nav[contains(@class,"sidebar-nav")]/a[@aria-current="page"]'));
            $this->assertSame('/'.$page, $xpath->query('//nav[contains(@class,"sidebar-nav")]/a[@aria-current="page"]')->item(0)->getAttribute('href'));
        }
        $this->assertSame([], $queries, 'Rendering UI must never read any Customer or primary database.');
        Http::assertNothingSent();
        Mail::assertNothingSent();
        $directory = storage_path('app/private/customer-account-phase2/preview');
        if (!is_dir($directory)) mkdir($directory, 0700, true);
        file_put_contents($directory.'/'.$page.'.html', $response->getContent());
    }

    public function test_settings_has_only_an_honest_browser_preference_and_readonly_system_values(): void
    {
        $this->get('/settings')->assertOk()->assertSee('aurel_account_motion', false)
            ->assertSee('Tùy chọn chỉ lưu trên trình duyệt này.')
            ->assertSee('Tiếng Việt')->assertSee('VND')->assertDontSee('type="checkbox"', false);
    }

    public function test_old_account_urls_remain_unique_and_payment_page_is_not_enabled_by_ui(): void
    {
        foreach (self::pages() as [$page]) {
            $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn($route) => $route->uri() === $page && in_array('GET', $route->methods()));
            $this->assertCount(1, $routes);
        }
        $this->get('/bookings')->assertOk()->assertSee('data-payment-page="false"', false);
        $this->assertFalse(config('roomtype-v2.final_integration'));
        $this->assertNull(config('roomtype-v2.production_manifest'));
        $this->get('/profile')->assertSee("location.hash === '#password'", false)->assertSee("location.replace('/password')", false);
    }
}
