<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminController;
use App\Http\Middleware\AdminMiddleware;
use App\Models\Customer;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    private Customer $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // All fixture accounts and tokens live only in RAM. No migrations or seeders run.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array', 'session.driver' => 'array',
        ]);
        DB::purge('sqlite');
        $connection = DB::connection('sqlite');
        $this->assertSame(':memory:', $connection->getDatabaseName());
        $this->assertSame('sqlite', $connection->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));
        $connection->getPdo()->exec('CREATE TABLE customer (
            id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT UNIQUE NOT NULL,
            phone TEXT, password_hash TEXT, role TEXT NOT NULL DEFAULT "user", avatar_url TEXT
        )');
        $connection->getPdo()->exec('CREATE TABLE personal_access_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tokenable_type TEXT NOT NULL, tokenable_id INTEGER NOT NULL,
            name TEXT NOT NULL, token TEXT UNIQUE NOT NULL, abilities TEXT, last_used_at DATETIME,
            expires_at DATETIME, created_at DATETIME, updated_at DATETIME
        )');
        $this->admin = $this->fixtureAccount('admin');
    }

    private function fixtureAccount(string $role, ?string $password = 'fixture-password'): Customer
    {
        return Customer::create([
            'name' => 'In-memory fixture', 'email' => $role . '@example.invalid', 'role' => $role,
            'password_hash' => $password === null ? null : Hash::make($password),
        ]);
    }

    private function api(string $method, string $path, ?string $token = null, array $data = []): TestResponse
    {
        // Each request must resolve its own guard instead of retaining the previous fixture user.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        return $this->json($method, $path, $data, $token ? ['Authorization' => 'Bearer ' . $token] : []);
    }

    public function test_all_admin_api_routes_except_login_require_authentication_and_admin_middleware(): void
    {
        $protected = 0;
        foreach ($this->app['router']->getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/admin/')) continue;
            if ($route->uri() === 'api/admin/login') {
                $this->assertNotContains('auth:admin', $route->gatherMiddleware());
                continue;
            }
            $this->assertContains('auth:admin', $route->gatherMiddleware(), $route->uri());
            $this->assertContains(AdminMiddleware::class, $route->gatherMiddleware(), $route->uri());
            $protected++;
        }
        $this->assertGreaterThan(0, $protected);
    }

    public function test_admin_login_replaces_only_admin_tokens_and_can_verify_the_new_session(): void
    {
        $customerToken = $this->admin->createToken('customer_token');
        $phoneToken = $this->admin->createToken('auth_token');
        $oldAdminToken = $this->admin->createToken('admin_token', ['admin']);
        $response = $this->api('POST', '/api/admin/login', null, [
            'email' => $this->admin->email, 'password' => 'fixture-password',
        ])->assertOk()->assertJsonPath('admin.role', 'admin')->assertJsonStructure(['token', 'admin']);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $customerToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $phoneToken->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldAdminToken->accessToken->id]);
        $this->assertArrayNotHasKey('password_hash', $response->json('admin'));
        $this->api('GET', '/api/admin/me', $response->json('token'))->assertOk()->assertJsonPath('role', 'admin');
    }

    public function test_customer_login_and_logout_preserve_the_admin_session(): void
    {
        $adminToken = $this->admin->createToken('admin_token', ['admin']);
        $oldCustomerToken = $this->admin->createToken('customer_token');
        $response = $this->api('POST', '/api/login', null, [
            'email' => $this->admin->email, 'password' => 'fixture-password',
        ])->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $adminToken->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldCustomerToken->accessToken->id]);
        $this->api('POST', '/api/logout', $response->json('token'))->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $adminToken->accessToken->id]);
        $this->api('GET', '/api/admin/me', $adminToken->plainTextToken)->assertOk();
    }

    public function test_customer_registration_login_and_logout_keep_the_admin_session_and_role_boundary(): void
    {
        $adminToken = $this->admin->createToken('admin_token', ['admin']);
        $registration = $this->api('POST', '/api/register', null, [
            'name' => 'Fixture Customer', 'email' => 'new-customer@example.invalid',
            'password' => 'fixture-password', 'role' => 'admin',
        ])->assertCreated()->assertJsonPath('user.role', 'user');
        $this->assertDatabaseHas('customer', ['email' => 'new-customer@example.invalid', 'role' => 'user']);
        $login = $this->api('POST', '/api/login', null, [
            'email' => 'new-customer@example.invalid', 'password' => 'fixture-password',
        ])->assertOk()->assertJsonPath('user.is_admin', false);
        $this->api('GET', '/api/admin/dashboard', $login->json('token'))->assertForbidden();
        $this->api('POST', '/api/logout', $login->json('token'))->assertOk();
        $this->api('POST', '/api/logout', $login->json('token'))->assertUnauthorized();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $adminToken->accessToken->id]);
        $this->api('GET', '/api/admin/me', $adminToken->plainTextToken)->assertOk();
    }

    public function test_admin_logout_deletes_only_the_current_admin_token(): void
    {
        $adminToken = $this->admin->createToken('admin_token', ['admin']);
        $otherAdminToken = $this->admin->createToken('admin_token', ['admin']);
        $customerToken = $this->admin->createToken('customer_token');
        $this->api('POST', '/api/admin/logout', $adminToken->plainTextToken)->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $adminToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherAdminToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $customerToken->accessToken->id]);
        $this->api('GET', '/api/admin/me', $adminToken->plainTextToken)->assertUnauthorized();
    }

    public function test_logout_endpoints_cannot_revoke_the_other_actor_session(): void
    {
        $adminToken = $this->admin->createToken('admin_token', ['admin']);
        $customerToken = $this->admin->createToken('customer_token');
        $this->api('POST', '/api/logout', $adminToken->plainTextToken)->assertForbidden();
        $this->api('POST', '/api/admin/logout', $customerToken->plainTextToken)->assertForbidden();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $adminToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $customerToken->accessToken->id]);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_cannot_log_in_or_access_admin_api(string $role): void
    {
        $customer = $this->fixtureAccount($role);
        $this->api('POST', '/api/admin/login', null, [
            'email' => $customer->email, 'password' => 'fixture-password',
        ])->assertUnauthorized();
        $token = $customer->createToken('admin_token', ['admin']);
        $this->api('GET', '/api/admin/locations', $token->plainTextToken)->assertForbidden();
        $this->api('GET', '/api/admin/dashboard', $token->plainTextToken)->assertForbidden();
    }

    public static function nonAdminRoles(): array
    {
        return [['user'], ['staff'], ['manager'], ['superadmin']];
    }

    public function test_customer_and_incorrectly_scoped_tokens_cannot_access_admin_api(): void
    {
        foreach (['customer_token' => ['*'], 'admin_token' => ['customer']] as $name => $abilities) {
            $token = $this->admin->createToken($name, $abilities);
            $this->api('GET', '/api/admin/me', $token->plainTextToken)->assertForbidden();
            $this->api('GET', '/api/admin/locations', $token->plainTextToken)->assertForbidden();
        }
    }

    public function test_missing_unknown_and_expired_tokens_are_rejected_without_redirects(): void
    {
        foreach ([null, 'not-issued-fixture-token'] as $token) {
            $this->api('GET', '/api/admin/me', $token)->assertUnauthorized()->assertHeaderMissing('Location');
            $this->api('GET', '/api/admin/dashboard', $token)->assertUnauthorized();
        }
        $expired = $this->admin->createToken('admin_token', ['admin'], now()->subMinute());
        $this->api('GET', '/api/admin/me', $expired->plainTextToken)->assertUnauthorized();
    }

    public function test_web_session_without_an_admin_bearer_token_is_rejected(): void
    {
        $this->actingAs($this->admin, 'web')->getJson('/api/admin/me')->assertForbidden();
        $this->getJson('/api/logout')->assertMethodNotAllowed();
        $this->postJson('/api/logout')->assertForbidden();
    }

    public function test_invalid_credentials_and_missing_fields_do_not_revoke_existing_tokens(): void
    {
        $token = $this->admin->createToken('admin_token', ['admin']);
        $this->api('POST', '/api/admin/login')->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->api('POST', '/api/admin/login', null, [
            'email' => 'unknown@example.invalid', 'password' => 'fixture-password',
        ])->assertUnauthorized();
        $this->api('POST', '/api/admin/login', null, [
            'email' => $this->admin->email, 'password' => 'wrong-fixture-password',
        ])->assertUnauthorized();
        $this->api('POST', '/api/admin/login', null, [
            'email' => 'invalid-email', 'password' => 'fixture-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_admin_without_a_password_cannot_log_in(): void
    {
        $this->admin->update(['password_hash' => null]);
        $this->api('POST', '/api/admin/login', null, [
            'email' => $this->admin->email, 'password' => 'fixture-password',
        ])->assertUnauthorized();
    }

    public function test_verified_admin_can_reach_dashboard_controller(): void
    {
        // Business data is deliberately stubbed; the real route, Sanctum guard and middleware run.
        $controller = Mockery::mock(AdminController::class)->makePartial();
        $controller->shouldReceive('dashboard')->once()->andReturn(response()->json(['fixture' => true]));
        $this->app->instance(AdminController::class, $controller);
        $token = $this->admin->createToken('admin_token', ['admin']);
        $this->api('GET', '/api/admin/dashboard', $token->plainTextToken)->assertOk()->assertJsonPath('fixture', true);
    }

    public function test_admin_views_render_with_production_assets_and_hidden_dashboard(): void
    {
        $this->app->make(Vite::class)->useHotFile(public_path('nonexistent-auth-test.hot'));
        $this->get('/admin/login')->assertOk()->assertDontSee('resources/js/app.js')
            ->assertDontSee('roleGrid')->assertDontSee('selectRole')->assertDontSee('admin_staff');
        $this->get('/admin')->assertOk()->assertSee('id="admin-shell" hidden', false)
            ->assertDontSee('resources/js/app.js')->assertDontSee('js-staff-')->assertDontSee('admin_staff')
            ->assertSee('js-admin-name')->assertSee('js-admin-role')->assertDontSee('Nhân viên');
    }
}
