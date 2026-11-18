<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Support\CustomerSessions;
use App\Support\RoomTypeV2TestDatabase;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Runs the same account-only migration/API checks on RAM SQLite or an owned MySQL schema. */
class CustomerDobMigrationTest extends TestCase
{
    private Customer $customer;
    private Customer $other;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-19 08:00:00');
        Http::preventStrayRequests();
        Mail::fake();
        if (config('database.default') === 'sqlite') {
            $this->assertSame(':memory:', config('database.connections.sqlite.database'));
            DB::purge('sqlite');
        } else {
            $this->assertSame('mysql', config('database.default'));
            $settings = RoomTypeV2TestDatabase::settings();
            $db = RoomTypeV2TestDatabase::connection('mysql');
            $schema = null;
            foreach ($settings['schemas'] as $candidate) {
                if ((int) $db->selectOne('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=?', [$candidate])->n === 0) {
                    $schema = $candidate; break;
                }
            }
            if (!$schema) throw new \RuntimeException('No empty owned test schema.');
            config(['database.connections.mysql.database' => $schema]);
            DB::purge('mysql');
            RoomTypeV2TestDatabase::connection('mysql');
        }
        (require base_path('database/migrations/2026_04_02_013436_create_customer_table.php'))->up();
        (require base_path('vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php'))->up();
        (require base_path('database/migrations/2026_10_12_000009_create_password_reset_tokens_table.php'))->up();
        // Only the empty count relation used by GET /me; no Booking module is initialized.
        Schema::create('booking', function ($table) { $table->integer('id', true); $table->integer('customer_id'); });
        $this->customer = Customer::create(['name' => 'Synthetic DOB customer', 'email' => 'dob@example.test',
            'phone' => '0900000001', 'role' => 'admin', 'password_hash' => Hash::make('old-password')]);
        $this->other = Customer::create(['name' => 'Other synthetic DOB customer', 'email' => 'other-dob@example.test',
            'password_hash' => Hash::make('other-password')]);
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function migration()
    {
        return require base_path('database/migrations/2026_10_19_000002_add_dob_to_customer_table.php');
    }

    private function api(string $method, string $path, ?string $token = null, array $data = [])
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        return $this->json($method, $path, $data, $token ? ['Authorization' => 'Bearer '.$token] : []);
    }

    public function test_migration_adds_only_nullable_dob_and_preserves_existing_customer_data(): void
    {
        $before = DB::table('customer')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $columns = Schema::getColumnListing('customer');
        $this->migration()->up();
        $this->assertSame(['dob'], array_values(array_diff(Schema::getColumnListing('customer'), $columns)));
        $this->assertSame(0, Customer::whereNotNull('dob')->count());
        $after = DB::table('customer')->orderBy('id')->get()->map(function ($row) { $data = (array) $row; unset($data['dob']); return $data; })->all();
        $this->assertSame($before, $after);
        $customer = Customer::create(['name' => 'No birthday', 'email' => 'no-birthday@example.test', 'password_hash' => Hash::make('new-password')]);
        $this->assertNull($customer->fresh()->dob);
    }

    public function test_dob_round_trip_uses_calendar_date_and_missing_or_empty_values_have_clear_meaning(): void
    {
        $this->migration()->up();
        $token = CustomerSessions::issuePasswordToken($this->customer)->plainTextToken;
        $this->api('GET', '/api/me', $token)->assertOk()->assertJsonPath('dob', null)->assertJsonPath('profile_fields.dob', true);
        $this->api('PUT', '/api/me', $token, ['name' => 'Saved name', 'dob' => '2000-02-29'])->assertOk()->assertJsonPath('user.dob', '2000-02-29');
        $this->api('GET', '/api/me', $token)->assertOk()->assertJsonPath('dob', '2000-02-29');
        $this->assertSame('2000-02-29', $this->customer->fresh()->toArray()['dob']);
        $this->api('PUT', '/api/me', $token, ['name' => 'Saved name', 'phone' => null])->assertOk()->assertJsonPath('user.dob', '2000-02-29');
        foreach (['', null] as $empty) {
            $this->api('PUT', '/api/me', $token, ['name' => 'Saved name', 'dob' => $empty])->assertOk()->assertJsonPath('user.dob', null);
            $this->api('GET', '/api/me', $token)->assertOk()->assertJsonPath('dob', null);
        }
        $this->assertNull($this->other->fresh()->dob);
    }

    public function test_dob_validation_rejects_bad_format_impossible_and_future_dates_without_partial_save(): void
    {
        $this->migration()->up();
        $token = CustomerSessions::issuePasswordToken($this->customer)->plainTextToken;
        foreach (['2001-02-29', '2000-02-30', '19/10/2000', '2000-01-01T00:00:00Z', '2026-10-20', 20000101, ['2000-01-01']] as $date) {
            $this->api('PUT', '/api/me', $token, ['name' => 'Must not save', 'dob' => $date])->assertUnprocessable()->assertJsonValidationErrors('dob');
            $this->assertSame('Synthetic DOB customer', $this->customer->fresh()->name);
            $this->assertNull($this->customer->fresh()->dob);
        }
        $this->api('PUT', '/api/me', $token, ['name' => 'Saved name', 'dob' => '2026-10-19', 'id' => $this->other->id, 'customer_id' => $this->other->id])
            ->assertOk()->assertJsonPath('user.id', $this->customer->id)->assertJsonPath('user.dob', '2026-10-19');
        $this->assertNull($this->other->fresh()->dob);
    }

    public function test_pre_migration_profile_rejects_dob_but_allows_other_fields_then_works_after_migration(): void
    {
        $token = CustomerSessions::issuePasswordToken($this->customer)->plainTextToken;
        $this->api('GET', '/api/me', $token)->assertOk()->assertJsonPath('profile_fields.dob', false)->assertJsonPath('dob', null);
        $this->api('PUT', '/api/me', $token, ['name' => 'Must not save', 'dob' => '2000-01-01'])->assertUnprocessable()->assertJsonValidationErrors('dob');
        $this->assertSame('Synthetic DOB customer', $this->customer->fresh()->name);
        $this->api('PUT', '/api/me', $token, ['name' => 'Name without DOB', 'phone' => '0900000002'])->assertOk();
        $this->migration()->up();
        $this->api('GET', '/api/me', $token)->assertOk()->assertJsonPath('profile_fields.dob', true)->assertJsonPath('dob', null);
        $this->api('PUT', '/api/me', $token, ['name' => 'Name without DOB', 'dob' => '1990-01-02'])->assertOk();
        $this->api('GET', '/api/me', $token)->assertOk()->assertJsonPath('dob', '1990-01-02');
    }

    public function test_rollback_removes_only_dob_and_reapply_does_not_invent_values(): void
    {
        $before = $this->customer->fresh()->getAttributes();
        $migration = $this->migration();
        $migration->up();
        $this->customer->setAttribute('dob', '2000-01-01')->save();
        $migration->down();
        $this->assertFalse(Schema::hasColumn('customer', 'dob'));
        $this->assertSame($before, $this->customer->fresh()->getAttributes());
        $migration->up();
        $this->assertNull($this->customer->fresh()->dob);
    }

    public function test_existing_dob_is_not_silently_overwritten_or_adopted_by_migration(): void
    {
        $this->migration()->up();
        $this->customer->setAttribute('dob', '2000-01-01')->save();
        try {
            $this->migration()->up();
            $this->fail('An existing column must be reviewed before migration.');
        } catch (QueryException $exception) {
            $this->assertSame('2000-01-01', $this->customer->fresh()->dob->format('Y-m-d'));
        }
    }

    public function test_password_reset_after_dob_migration_preserves_profile_and_only_real_admin_sessions(): void
    {
        $this->migration()->up();
        $this->customer->setAttribute('dob', '2000-01-01')->save();
        $admin = $this->customer->createToken('admin_token', ['admin']);
        CustomerSessions::issueGoogleToken($this->customer);
        $this->customer->createToken('auth_token');
        $this->customer->createToken('admin_token', ['customer']);
        DB::table('password_reset_tokens')->insert(['email' => $this->customer->email, 'token' => Hash::make(str_repeat('r', 64)), 'created_at' => now()]);
        $this->api('POST', '/api/reset-password', null, ['email' => $this->customer->email, 'token' => str_repeat('r', 64),
            'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertOk();
        $this->assertSame([$admin->accessToken->id], $this->customer->tokens()->pluck('id')->all());
        $this->assertSame('2000-01-01', $this->customer->fresh()->dob->format('Y-m-d'));
        $this->assertSame(2, Customer::count());
        // No Customer sessions remain; another valid reset still preserves Admin.
        DB::table('password_reset_tokens')->insert(['email' => $this->customer->email, 'token' => Hash::make(str_repeat('s', 64)), 'created_at' => now()]);
        $this->api('POST', '/api/reset-password', null, ['email' => $this->customer->email, 'token' => str_repeat('s', 64),
            'password' => 'next-password', 'password_confirmation' => 'next-password'])->assertOk();
        $this->api('GET', '/api/admin/me', $admin->plainTextToken)->assertOk();
        $this->assertSame([$admin->accessToken->id], $this->customer->tokens()->pluck('id')->all());
    }
}
