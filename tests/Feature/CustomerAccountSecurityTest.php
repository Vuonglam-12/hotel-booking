<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Support\CustomerSessions;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Laravel\Sanctum\PersonalAccessToken;

class CustomerAccountSecurityTest extends TestCase
{
    private Customer $customer;
    private Customer $other;
    private int $expectedHttpRequests = 0;
    private ?\OpenSSLAsymmetricKey $googleSigningKey = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-10-19 08:00:00');
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::purge('sqlite');
        $db = DB::connection('sqlite');
        $this->assertSame(':memory:', $db->getDatabaseName());
        $db->unprepared('CREATE TABLE customer (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL, phone TEXT, password_hash TEXT, role TEXT DEFAULT "user", avatar_url TEXT, created_at DATETIME)');
        $db->unprepared('CREATE TABLE booking (id INTEGER PRIMARY KEY, customer_id INTEGER NOT NULL)');
        $db->unprepared('CREATE TABLE personal_access_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT,
            tokenable_type TEXT, tokenable_id INTEGER, name TEXT, token TEXT UNIQUE, abilities TEXT,
            last_used_at DATETIME, expires_at DATETIME, created_at DATETIME, updated_at DATETIME)');
        $db->unprepared('CREATE TABLE password_reset_tokens (email TEXT PRIMARY KEY, token TEXT, created_at DATETIME)');
        Http::preventStrayRequests();
        Mail::fake();
        $this->customer = Customer::create(['name' => 'Synthetic customer', 'email' => 'customer@example.test',
            'phone' => '0900000001', 'password_hash' => Hash::make('old-password'), 'role' => 'admin']);
        $this->other = Customer::create(['name' => 'Other synthetic customer', 'email' => 'other@example.test',
            'password_hash' => Hash::make('other-password')]);
    }

    protected function tearDown(): void
    {
        Http::assertSentCount($this->expectedHttpRequests);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function api(string $method, string $path, ?string $token = null, array $data = [])
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        return $this->json($method, $path, $data, $token ? ['Authorization' => 'Bearer '.$token] : []);
    }

    private function token(Customer $customer, string $name = 'customer_token'): string
    {
        return $customer->createToken($name, $name === 'admin_token' ? ['admin'] : ['*'])->plainTextToken;
    }

    private function resetRecord(string $secret, int $minutesAgo = 0): void
    {
        DB::table('password_reset_tokens')->updateOrInsert(['email' => $this->customer->email], [
            'token' => Hash::make($secret), 'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_logout_revokes_only_current_customer_token_and_refuses_admin_token(): void
    {
        $current = $this->token($this->customer);
        $peer = $this->token($this->customer);
        $google = CustomerSessions::issueGoogleToken($this->customer)->plainTextToken;
        $admin = $this->token($this->customer, 'admin_token');
        $other = $this->token($this->other);
        $this->api('POST', '/api/logout', $current)->assertOk();
        $this->api('GET', '/api/me', $current)->assertUnauthorized();
        foreach ([$peer, $google, $other] as $token) $this->api('GET', '/api/me', $token)->assertOk();
        $this->api('POST', '/api/logout', $admin)->assertForbidden();
        $this->api('GET', '/api/admin/me', $admin)->assertOk();
    }

    public function test_password_change_revokes_other_customer_sessions_but_preserves_current_admin_and_other_customer(): void
    {
        $current = $this->token($this->customer);
        $peer = $this->token($this->customer);
        $google = CustomerSessions::issueGoogleToken($this->customer)->plainTextToken;
        $admin = $this->token($this->customer, 'admin_token');
        $other = $this->token($this->other);
        $this->resetRecord(str_repeat('r', 64));
        $this->api('POST', '/api/me/password', $current, [
            'current_password' => 'old-password', 'new_password' => 'new-password', 'new_password_confirmation' => 'new-password',
        ])->assertOk();
        $this->assertTrue(Hash::check('new-password', $this->customer->fresh()->password_hash));
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        $this->api('GET', '/api/me', $peer)->assertUnauthorized();
        $this->api('GET', '/api/me', $google)->assertUnauthorized();
        foreach ([$current, $other] as $token) $this->api('GET', '/api/me', $token)->assertOk();
        $this->api('GET', '/api/admin/me', $admin)->assertOk();
        $this->assertTrue(Hash::check('other-password', $this->other->fresh()->password_hash));
    }

    public function test_wrong_current_password_and_admin_token_cannot_change_password(): void
    {
        $token = $this->token($this->customer);
        $data = ['current_password' => 'wrong-password', 'new_password' => 'new-password', 'new_password_confirmation' => 'new-password'];
        for ($index = 0; $index < 5; $index++) $this->api('POST', '/api/me/password', $token, $data)->assertUnprocessable();
        $this->api('POST', '/api/me/password', $token, $data)->assertStatus(429);
        $this->api('POST', '/api/me/password', $this->token($this->customer, 'admin_token'), $data)->assertForbidden();
        $this->assertTrue(Hash::check('old-password', $this->customer->fresh()->password_hash));
    }

    public function test_password_validation_prevents_short_oversized_same_and_mismatched_values(): void
    {
        $token = $this->token($this->customer);
        foreach (['short', str_repeat('p', 73), 'old-password'] as $password) {
            $this->api('POST', '/api/me/password', $token, ['current_password' => 'old-password',
                'new_password' => $password, 'new_password_confirmation' => $password])->assertUnprocessable();
        }
        $this->api('POST', '/api/me/password', $token, ['current_password' => 'old-password',
            'new_password' => 'new-password', 'new_password_confirmation' => 'different-password'])->assertUnprocessable();
    }

    public function test_password_login_issues_expiring_customer_token_without_revoking_google_or_admin(): void
    {
        $google = CustomerSessions::issueGoogleToken($this->customer)->plainTextToken;
        $admin = $this->token($this->customer, 'admin_token');
        foreach ([['/api/login', ['email' => $this->customer->email]], ['/api/auth/login-phone', ['phone' => $this->customer->phone]]] as [$path, $data]) {
            $token = $this->api('POST', $path, null, $data + ['password' => 'old-password'])->assertOk()->json('token');
            $record = $this->customer->tokens()->where('name', CustomerSessions::PASSWORD_TOKEN)->sole();
            $this->assertTrue($record->expires_at->equalTo(now()->addDays(7)));
            $this->api('GET', '/api/me', $google)->assertOk();
            $this->api('GET', '/api/admin/me', $admin)->assertOk();
            Carbon::setTestNow(now()->addDays(8));
            $this->api('GET', '/api/me', $token)->assertUnauthorized();
            Carbon::setTestNow('2026-10-19 08:00:00');
        }
    }

    public function test_missing_dob_schema_rejects_birthdate_without_partial_save_or_false_success(): void
    {
        $token = $this->token($this->customer);
        $this->api('GET', '/api/me', $token)->assertOk()->assertJsonPath('profile_fields.dob', false)->assertJsonPath('dob', null);
        $this->api('PUT', '/api/me', $token, ['name' => 'Changed', 'dob' => '2000-01-01'])->assertUnprocessable()->assertJsonValidationErrors('dob');
        $this->assertSame('Synthetic customer', $this->customer->fresh()->name);
        $this->api('PUT', '/api/me', $token, ['name' => 'Saved name', 'phone' => '0900000002'])->assertOk();
        $this->assertSame('Saved name', $this->customer->fresh()->name);
    }

    public function test_existing_dob_column_is_saved_explicitly_and_future_dates_are_rejected(): void
    {
        Schema::table('customer', fn ($table) => $table->date('dob')->nullable());
        $token = $this->token($this->customer);
        $this->api('PUT', '/api/me', $token, ['name' => 'Saved name', 'dob' => '2000-01-01'])->assertOk()->assertJsonPath('user.dob', '2000-01-01');
        $this->api('GET', '/api/me', $token)->assertOk()->assertJsonPath('profile_fields.dob', true)->assertJsonPath('dob', '2000-01-01');
        foreach (['2030-01-01', 'not-a-date'] as $date) $this->api('PUT', '/api/me', $token, ['name' => 'Saved name', 'dob' => $date])->assertUnprocessable();
        $this->assertSame('2000-01-01', $this->customer->fresh()->dob->format('Y-m-d'));
    }

    public function test_profile_ownership_and_actual_booking_count_cannot_be_overridden(): void
    {
        DB::table('booking')->insert([['id' => 1, 'customer_id' => $this->customer->id], ['id' => 2, 'customer_id' => $this->other->id]]);
        $token = $this->token($this->customer);
        $other = $this->other->fresh()->getAttributes();
        $this->api('GET', '/api/me', $token)->assertOk()->assertJsonPath('bookings', 1);
        $this->api('PUT', '/api/me', $token, ['name' => 'Own updated name', 'id' => $this->other->id,
            'email' => $this->other->email, 'role' => 'user', 'password_hash' => 'bad'])->assertOk();
        $this->assertSame($other, $this->other->fresh()->getAttributes());
        $this->assertSame('admin', $this->customer->fresh()->role);
        $this->assertSame('customer@example.test', $this->customer->fresh()->email);
        $this->assertTrue(Hash::check('old-password', $this->customer->fresh()->password_hash));
    }

    public function test_avatar_upload_remains_scoped_to_customer_and_validated(): void
    {
        Storage::fake('public');
        $token = $this->token($this->customer);
        $this->api('POST', '/api/me/avatar', $token, ['avatar' => UploadedFile::fake()->image('avatar.png')])->assertOk();
        $this->assertNotNull($this->customer->fresh()->avatar_url);
        $this->assertNull($this->other->fresh()->avatar_url);
        $this->api('POST', '/api/me/avatar', $token, ['avatar' => UploadedFile::fake()->create('unsafe.svg', 5, 'image/svg+xml')])->assertUnprocessable();
    }

    public function test_recovery_requests_hide_account_existence_and_use_identical_cooldowns(): void
    {
        foreach (['/api/forgot-password-otp', '/api/forgot-password'] as $path) {
            app('cache')->clear();
            $known = $this->api('POST', $path, null, ['email' => $this->customer->email])->assertOk();
            $unknown = $this->api('POST', $path, null, ['email' => 'unknown@example.test'])->assertOk();
            $this->assertSame($known->json(), $unknown->json());
            $this->assertSame($known->headers->get('Retry-After'), $unknown->headers->get('Retry-After'));
            foreach ([$this->customer->email, 'unknown@example.test'] as $email) $this->api('POST', $path, null, compact('email'))->assertStatus(429);
            $this->assertSame(1, DB::table('password_reset_tokens')->count());
        }
    }

    public function test_recovery_delivery_failure_does_not_disclose_account_existence(): void
    {
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('Synthetic mail failure'));
        $known = $this->api('POST', '/api/forgot-password-otp', null, ['email' => $this->customer->email])->assertOk();
        $unknown = $this->api('POST', '/api/forgot-password-otp', null, ['email' => 'unknown@example.test'])->assertOk();
        $this->assertSame($known->json(), $unknown->json());
    }

    public function test_otp_attempt_limit_is_per_email_across_clients_and_resends_do_not_clear_it(): void
    {
        $this->resetRecord('123456');
        for ($index = 0; $index < 5; $index++) $this->api('POST', '/api/forgot-password-verify-otp', null,
            ['email' => $this->customer->email, 'otp' => '999999'])->assertUnprocessable();
        $this->api('POST', '/api/forgot-password-otp', null, ['email' => $this->customer->email])->assertOk();
        $this->api('POST', '/api/forgot-password-verify-otp', null,
            ['email' => strtoupper($this->customer->email), 'otp' => '123456'])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_expired_unknown_and_wrong_otp_have_identical_errors_and_raw_otp_cannot_reset(): void
    {
        $this->resetRecord('123456', 11);
        $known = $this->api('POST', '/api/forgot-password-verify-otp', null, ['email' => $this->customer->email, 'otp' => '123456'])->assertUnprocessable();
        $unknown = $this->api('POST', '/api/forgot-password-verify-otp', null, ['email' => 'unknown@example.test', 'otp' => '123456'])->assertUnprocessable();
        $this->assertSame($known->json(), $unknown->json());
        $this->api('POST', '/api/reset-password', null, ['email' => $this->customer->email, 'token' => '123456',
            'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertUnprocessable();
        $this->assertTrue(Hash::check('old-password', $this->customer->fresh()->password_hash));
    }

    public function test_verified_otp_is_single_use_and_reset_revokes_google_but_preserves_admin_and_other_customer(): void
    {
        $passwordToken = $this->token($this->customer);
        $google = CustomerSessions::issueGoogleToken($this->customer)->plainTextToken;
        $admin = $this->token($this->customer, 'admin_token');
        $other = $this->token($this->other);
        $this->resetRecord('123456');
        $token = $this->api('POST', '/api/forgot-password-verify-otp', null, ['email' => $this->customer->email,
            'otp' => '123456'])->assertOk()->json('reset_token');
        $this->api('POST', '/api/forgot-password-verify-otp', null, ['email' => $this->customer->email, 'otp' => '123456'])->assertUnprocessable();
        $data = ['email' => $this->customer->email, 'token' => $token, 'password' => 'new-password', 'password_confirmation' => 'new-password'];
        $this->api('POST', '/api/reset-password', null, $data)->assertOk();
        $this->api('POST', '/api/reset-password', null, $data)->assertUnprocessable();
        $this->api('GET', '/api/me', $passwordToken)->assertUnauthorized();
        $this->api('GET', '/api/me', $google)->assertUnauthorized();
        $this->api('GET', '/api/me', $other)->assertOk();
        $this->api('GET', '/api/admin/me', $admin)->assertOk();
    }

    public function test_reset_link_expiry_attempt_limit_and_validation_do_not_modify_password(): void
    {
        $this->resetRecord(str_repeat('r', 64), 61);
        $data = ['email' => $this->customer->email, 'token' => str_repeat('r', 64), 'password' => 'new-password', 'password_confirmation' => 'new-password'];
        for ($index = 0; $index < 5; $index++) $this->api('POST', '/api/reset-password', null, $data)->assertUnprocessable();
        $this->api('POST', '/api/reset-password', null, $data)->assertStatus(429);
        $this->api('POST', '/api/forgot-password-verify-otp', null, ['email' => $this->customer->email, 'otp' => 'abcdef'])->assertUnprocessable();
        $this->assertTrue(Hash::check('old-password', $this->customer->fresh()->password_hash));
    }

    public function test_account_views_do_not_claim_unsupported_business_or_security_features(): void
    {
        $profile = $this->get('/profile')->assertOk();
        foreach (['Thành viên Bạc', 'Visa ****', 'MoMo ****', 'id="activityList"', 'id="stat-points"', 'id="stat-vouchers"'] as $text) $profile->assertDontSee($text, false);
        $this->get('/password')->assertOk()->assertDontSee('2FA', false)->assertDontSee('Đã bật', false);
        $this->get('/settings')->assertOk();
    }

    public function test_legacy_itinerary_is_owned_and_read_only(): void
    {
        $this->itinerarySchema();
        $token = $this->token($this->customer);
        $otherToken = $this->token($this->other);
        $this->api('GET', '/api/itineraries/1', $otherToken)->assertNotFound();
        $this->api('PUT', '/api/itineraries/1', $otherToken, ['status' => 'saved'])->assertNotFound();
        $this->api('DELETE', '/api/itineraries/1', $otherToken)->assertNotFound();
        $this->api('GET', '/api/itineraries', $otherToken)->assertOk()->assertExactJson([]);
        $this->api('PUT', '/api/itineraries/1', $token, ['title' => ['invalid'], 'status' => 'bogus', 'estimated_budget' => -1])->assertConflict();
        $title = '<img src=x onerror=alert(1)> Văn bản tiếng Việt';
        $this->api('PUT', '/api/itineraries/1', $token, ['title' => $title, 'status' => 'saved', 'customer_id' => $this->other->id])
            ->assertConflict()->assertJsonPath('code', 'legacy_itinerary_read_only');
        $this->assertSame($this->customer->id, DB::table('itinerary')->value('customer_id'));
        $this->assertSame('Synthetic itinerary', DB::table('itinerary')->value('title'));
    }

    public function test_legacy_itinerary_item_cross_customer_access_and_writes_are_refused(): void
    {
        $this->itinerarySchema();
        $other = $this->token($this->other);
        $this->api('PUT', '/api/itineraries/1/items/1', $other, ['title' => 'Attack'])->assertNotFound();
        $this->api('DELETE', '/api/itineraries/1/items/1', $other)->assertNotFound();
        $token = $this->token($this->customer);
        $this->api('PUT', '/api/itineraries/1/items/1', $token, ['item_type' => '<img>', 'start_time' => '99:99', 'estimated_cost' => -1])->assertConflict();
        $this->api('PUT', '/api/itineraries/1/items/1', $token, ['title' => 'Cà phê', 'item_type' => 'restaurant', 'start_time' => '08:30', 'estimated_cost' => 50000])
            ->assertConflict()->assertJsonPath('code', 'legacy_itinerary_read_only');
        $this->api('PUT', '/api/itineraries/1/items/1', $token, ['start_time' => '08:30:00', 'item_type' => 'destination'])->assertConflict();
        $this->api('PUT', '/api/itineraries/1/items/1', $token, ['estimated_cost' => 100000000])->assertConflict();
        $this->assertSame('Synthetic item', DB::table('itinerary_item')->value('title'));
    }

    private function itinerarySchema(): void
    {
        DB::unprepared('CREATE TABLE itinerary (id INTEGER PRIMARY KEY, customer_id INTEGER, title TEXT, location_id INTEGER,
            total_days INTEGER, start_date DATE, end_date DATE, estimated_budget DECIMAL, status TEXT, created_at DATETIME)');
        DB::unprepared('CREATE TABLE itinerary_item (id INTEGER PRIMARY KEY, itinerary_id INTEGER, day_number INTEGER,
            item_type TEXT, title TEXT, description TEXT, start_time TEXT, end_time TEXT, estimated_cost DECIMAL, order_in_day INTEGER)');
        DB::table('itinerary')->insert(['id' => 1, 'customer_id' => $this->customer->id, 'title' => 'Synthetic itinerary', 'total_days' => 1, 'status' => 'draft']);
        DB::table('itinerary_item')->insert(['id' => 1, 'itinerary_id' => 1, 'day_number' => 1, 'item_type' => 'attraction', 'title' => 'Synthetic item', 'order_in_day' => 1]);
    }

    public function test_render_account_previews_without_real_accounts_or_database(): void
    {
        $directory = storage_path('app/private/customer-account-phase1-completion/preview');
        if (!is_dir($directory)) mkdir($directory, 0700, true);
        foreach (['profile', 'password', 'bookings', 'itineraries', 'settings'] as $page) {
            file_put_contents($directory.'/'.$page.'.html', $this->get('/'.$page)->assertOk()->getContent());
        }
        file_put_contents($directory.'/home-chat.html', '<div id="chat-messages"></div><input id="chat-input">'.view('partials.home.chatbot-script')->render());
        foreach (['login', 'register'] as $page) {
            file_put_contents($directory.'/'.$page.'.html', $this->get('/'.$page)->assertOk()->getContent());
        }
        $email = '</script><script>window.unsafeReset=1</script>@example.test';
        $reset = $this->get('/reset-password/'.str_repeat('r', 64).'?email='.urlencode($email))->assertOk();
        $reset->assertDontSee($email, false);
        file_put_contents($directory.'/reset-password.html', $reset->getContent());
    }

    public function test_missing_reset_store_never_exposes_whether_email_exists(): void
    {
        Schema::drop('password_reset_tokens');
        foreach (['/api/forgot-password', '/api/forgot-password-otp', '/api/forgot-password-verify-otp', '/api/reset-password'] as $route) {
            $responses = [];
            foreach ([$this->customer->email, 'unknown@example.test'] as $email) {
                $responses[] = $this->api('POST', $route, null, ['email' => $email, 'otp' => '123456', 'token' => str_repeat('r', 64),
                    'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertStatus(503)->json();
            }
            $this->assertSame($responses[0], $responses[1]);
        }
    }

    public function test_new_password_tokens_are_classified_by_server_for_registration_email_and_phone(): void
    {
        $forged = ['auth_method' => 'google', 'token_name' => 'admin_token', 'abilities' => ['admin']];
        $registration = $this->api('POST', '/api/register', null, $forged + [
            'name' => 'Synthetic registration', 'email' => 'registration@example.test', 'password' => 'new-password',
        ])->assertCreated();
        $tokens = [$registration->json('token')];
        foreach ([['/api/login', ['email' => $this->customer->email]], ['/api/auth/login-phone', ['phone' => $this->customer->phone]]] as [$path, $identity]) {
            $tokens[] = $this->api('POST', $path, null, $forged + $identity + ['password' => 'old-password'])->assertOk()->json('token');
        }
        // The second password login replaces the first known password token.
        $this->api('GET', '/api/me', $tokens[1])->assertUnauthorized();
        foreach ([$tokens[0], $tokens[2]] as $token) {
            $record = PersonalAccessToken::findToken($token);
            $this->assertSame(CustomerSessions::PASSWORD_TOKEN, $record->name);
            $this->assertSame(['customer', 'auth:password'], $record->abilities);
            $this->assertFalse($record->can('admin'));
            $this->assertTrue($record->expires_at->equalTo(now()->addDays(7)));
        }
    }

    public function test_logout_handles_each_customer_token_kind_without_revoking_peers_or_admin(): void
    {
        $admin = $this->token($this->customer, 'admin_token');
        $peer = $this->token($this->customer, 'auth_token');
        $other = $this->token($this->other);
        $tokens = [CustomerSessions::issuePasswordToken($this->customer)->plainTextToken,
            CustomerSessions::issueGoogleToken($this->customer)->plainTextToken,
            $this->token($this->customer, 'auth_token'), $this->token($this->customer, 'old-unknown-session')];
        foreach ($tokens as $token) {
            $this->api('POST', '/api/logout', $token)->assertOk()->assertJsonPath('revoked', true);
            $this->api('GET', '/api/me', $token)->assertUnauthorized();
            foreach ([$peer, $other] as $remaining) $this->api('GET', '/api/me', $remaining)->assertOk();
            $this->api('GET', '/api/admin/me', $admin)->assertOk();
        }
    }

    public function test_reset_revokes_all_customer_token_kinds_without_guessing_legacy_origin(): void
    {
        $admin = $this->customer->createToken('admin_token', ['admin']);
        $wildcardAdmin = $this->customer->createToken('admin_token', ['*']);
        $adminBefore = $this->customer->tokens()->whereIn('id', [$admin->accessToken->id, $wildcardAdmin->accessToken->id])->get()->toArray();
        $other = $this->token($this->other, 'auth_token');
        $foreignActor = PersonalAccessToken::forceCreate(['tokenable_type' => 'UnrelatedSyntheticPrincipal',
            'tokenable_id' => $this->customer->id, 'name' => 'auth_token', 'token' => hash('sha256', 'synthetic-foreign-actor'), 'abilities' => ['*']]);
        $tokens = [CustomerSessions::issuePasswordToken($this->customer)->plainTextToken,
            CustomerSessions::issueGoogleToken($this->customer)->plainTextToken,
            $this->token($this->customer), $this->token($this->customer, 'auth_token'),
            $this->token($this->customer, 'auth_token'), $this->token($this->customer, 'unknown-session'),
            $this->customer->createToken('admin_token', ['customer'])->plainTextToken,
            $this->customer->createToken('unknown-admin-label', ['admin'])->plainTextToken,
            $this->customer->createToken('admin_token', [])->plainTextToken];
        $this->customer->createToken('expired-unknown', ['customer'], now()->subDay());
        $this->customer->createToken('malformed-legacy')->accessToken->forceFill(['abilities' => null])->save();
        $identityBefore = $this->customer->only(['id', 'name', 'email', 'role']);
        $this->resetRecord(str_repeat('r', 64));
        $this->api('POST', '/api/reset-password', null, ['email' => $this->customer->email, 'token' => str_repeat('r', 64),
            'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertOk();
        $this->assertSame([$admin->accessToken->id, $wildcardAdmin->accessToken->id], $this->customer->tokens()->orderBy('id')->pluck('id')->all());
        $this->assertSame($adminBefore, $this->customer->tokens()->orderBy('id')->get()->toArray());
        $this->assertSame($identityBefore, $this->customer->fresh()->only(['id', 'name', 'email', 'role']));
        $this->assertSame(2, Customer::count());
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreignActor->id]);
        foreach ($tokens as $token) $this->api('GET', '/api/me', $token)->assertUnauthorized();
        $this->api('GET', '/api/me', $other)->assertOk();
        foreach ([$admin->plainTextToken, $wildcardAdmin->plainTextToken] as $token) $this->api('GET', '/api/admin/me', $token)->assertOk();
    }

    public function test_password_change_from_unclassified_current_session_keeps_only_that_customer_session(): void
    {
        $current = $this->token($this->customer, 'auth_token');
        $peerLegacy = $this->token($this->customer, 'auth_token');
        $peerGoogle = CustomerSessions::issueGoogleToken($this->customer)->plainTextToken;
        $peerUnknown = $this->customer->createToken('admin_token', ['customer'])->plainTextToken;
        $admin = $this->token($this->customer, 'admin_token');
        $other = $this->token($this->other);
        $this->api('POST', '/api/me/password', $current, ['current_password' => 'old-password',
            'new_password' => 'new-password', 'new_password_confirmation' => 'new-password'])->assertOk();
        foreach ([$peerLegacy, $peerGoogle, $peerUnknown] as $token) $this->api('GET', '/api/me', $token)->assertUnauthorized();
        foreach ([$current, $other] as $token) $this->api('GET', '/api/me', $token)->assertOk();
        $this->assertSame('auth_token', PersonalAccessToken::findToken($current)->name);
        $this->api('GET', '/api/admin/me', $admin)->assertOk();
    }

    public function test_customer_cannot_preserve_a_session_using_admin_name_or_ability_on_non_admin_account(): void
    {
        $forged = $this->other->createToken('admin_token', ['admin'])->plainTextToken;
        $realAdmin = $this->token($this->customer, 'admin_token');
        $this->api('GET', '/api/admin/me', $forged)->assertForbidden();
        DB::table('password_reset_tokens')->insert(['email' => $this->other->email, 'token' => Hash::make(str_repeat('r', 64)), 'created_at' => now()]);
        $this->api('POST', '/api/reset-password', null, ['email' => $this->other->email, 'token' => str_repeat('r', 64),
            'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertOk();
        $this->api('GET', '/api/me', $forged)->assertUnauthorized();
        $this->api('GET', '/api/admin/me', $realAdmin)->assertOk();
        $this->assertSame('user', $this->other->fresh()->role);
    }

    public function test_failed_password_and_reset_requests_do_not_revoke_any_session(): void
    {
        $current = $this->token($this->customer);
        $this->token($this->customer, 'auth_token');
        $this->token($this->customer, 'admin_token');
        CustomerSessions::issueGoogleToken($this->customer);
        $ids = $this->customer->tokens()->orderBy('id')->pluck('id')->all();
        $this->resetRecord(str_repeat('r', 64));
        $this->api('POST', '/api/me/password', $current, ['current_password' => 'wrong-password',
            'new_password' => 'new-password', 'new_password_confirmation' => 'new-password'])->assertUnprocessable();
        $this->api('POST', '/api/reset-password', null, ['email' => $this->customer->email, 'token' => str_repeat('x', 64),
            'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertUnprocessable();
        $this->assertSame($ids, $this->customer->tokens()->orderBy('id')->pluck('id')->all());
        $this->assertTrue(Hash::check('old-password', $this->customer->fresh()->password_hash));
    }

    public function test_revocation_failure_rolls_back_password_and_preserves_reset_credential(): void
    {
        Log::spy();
        $current = $this->token($this->customer);
        $this->token($this->customer, 'auth_token');
        $this->token($this->customer, 'admin_token');
        $ids = $this->customer->tokens()->orderBy('id')->pluck('id')->all();
        $this->resetRecord(str_repeat('r', 64));
        DB::unprepared("CREATE TRIGGER synthetic_token_delete_failure BEFORE DELETE ON personal_access_tokens
            BEGIN SELECT RAISE(ABORT, 'Synthetic revocation failure'); END");
        $this->api('POST', '/api/me/password', $current, ['current_password' => 'old-password',
            'new_password' => 'new-password', 'new_password_confirmation' => 'new-password'])->assertStatus(500);
        $this->api('POST', '/api/reset-password', null, ['email' => $this->customer->email, 'token' => str_repeat('r', 64),
            'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertStatus(500);
        $this->assertTrue(Hash::check('old-password', $this->customer->fresh()->password_hash));
        $this->assertTrue(Hash::check(str_repeat('r', 64), DB::table('password_reset_tokens')->value('token')));
        $this->assertSame($ids, $this->customer->tokens()->orderBy('id')->pluck('id')->all());
    }

    public function test_verified_google_can_sign_in_again_to_same_customer_after_reset_without_admin_privileges(): void
    {
        $legacy = $this->token($this->customer, 'auth_token');
        $admin = $this->token($this->customer, 'admin_token');
        $credential = $this->googleCredential($this->customer->email);
        $google = $this->api('POST', '/api/auth/google', null, ['credential' => $credential, 'token_name' => 'admin_token', 'abilities' => ['admin']])
            ->assertOk()->assertJsonPath('user.id', $this->customer->id)->json('token');
        $record = PersonalAccessToken::findToken($google);
        $this->assertSame(CustomerSessions::GOOGLE_TOKEN, $record->name);
        $this->assertSame(['customer', 'auth:google'], $record->abilities);
        $this->assertTrue($record->expires_at->equalTo(now()->addDays(7)));
        $this->api('GET', '/api/admin/me', $google)->assertForbidden();
        $this->resetRecord(str_repeat('r', 64));
        $this->api('POST', '/api/reset-password', null, ['email' => $this->customer->email, 'token' => str_repeat('r', 64),
            'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertOk();
        foreach ([$legacy, $google] as $token) $this->api('GET', '/api/me', $token)->assertUnauthorized();
        $again = $this->api('POST', '/api/auth/google', null, ['credential' => $credential])->assertOk()
            ->assertJsonPath('user.id', $this->customer->id)->json('token');
        $this->assertSame(2, Customer::count());
        $this->assertTrue(Hash::check('new-password', $this->customer->fresh()->password_hash));
        $this->api('GET', '/api/me', $again)->assertOk();
        $this->api('GET', '/api/admin/me', $again)->assertForbidden();
        $this->api('GET', '/api/admin/me', $admin)->assertOk();
        Carbon::setTestNow(now()->addDays(8));
        $this->api('GET', '/api/me', $again)->assertUnauthorized();
    }

    public function test_invalid_google_signature_cannot_issue_a_classified_token_or_revoke_sessions(): void
    {
        $this->token($this->customer, 'auth_token');
        $parts = explode('.', $this->googleCredential($this->customer->email));
        $parts[2][0] = $parts[2][0] === 'A' ? 'B' : 'A';
        $before = $this->customer->tokens()->get()->toArray();
        $this->api('POST', '/api/auth/google', null, ['credential' => implode('.', $parts)])->assertUnauthorized()->assertJsonMissingPath('token');
        $this->assertSame($before, $this->customer->tokens()->get()->toArray());
    }

    private function googleCredential(string $email): string
    {
        config(['services.google.client_id' => 'synthetic-aurel-customer.apps.googleusercontent.com']);
        if (!$this->googleSigningKey) {
            $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => dirname(PHP_BINARY).'/extras/ssl/openssl.cnf'];
            $this->googleSigningKey = openssl_pkey_new($options);
            $this->assertNotFalse($this->googleSigningKey);
            $csr = openssl_csr_new(['commonName' => 'Synthetic customer session test'], $this->googleSigningKey, $options);
            $certificate = openssl_csr_sign($csr, null, $this->googleSigningKey, 1, $options);
            openssl_x509_export($certificate, $pem);
            Cache::forget('aurel.google.identity.certificates.v1');
            Http::fake([\App\Services\GoogleIdentityVerifier::CERTIFICATES_URL => Http::response(['synthetic-customer-key' => $pem], 200, ['Cache-Control' => 'max-age=3600'])]);
            $this->expectedHttpRequests = 1;
        }
        $encode = fn ($value) => rtrim(strtr(base64_encode(json_encode($value)), '+/', '-_'), '=');
        $message = $encode(['alg' => 'RS256', 'kid' => 'synthetic-customer-key']).'.'.$encode([
            'iss' => 'https://accounts.google.com', 'aud' => config('services.google.client_id'), 'sub' => 'synthetic-customer-subject',
            'email' => $email, 'email_verified' => true, 'hd' => 'example.test', 'iat' => now()->timestamp, 'exp' => now()->addMinutes(5)->timestamp,
        ]);
        openssl_sign($message, $signature, $this->googleSigningKey, OPENSSL_ALGO_SHA256);
        return $message.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
