<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ItineraryFixture
{
    public static function settings(): array
    {
        $path = getenv('AUREL_ITINERARY_TEST_SETTINGS');
        $resolved = is_string($path) ? realpath($path) : false;
        $allowed = realpath(dirname(__DIR__, 2).'/storage/app/private/roomtype-v2-tests');
        if (! $resolved || ! $allowed || ! str_starts_with($resolved, $allowed.DIRECTORY_SEPARATOR)
            || basename($resolved) !== 'itinerary-test-environment.json') {
            throw new \RuntimeException('Missing owned itinerary test settings; application DB is never a fallback.');
        }
        $settings = json_decode(file_get_contents($resolved), true, 512, JSON_THROW_ON_ERROR);
        if (($settings['host'] ?? '') !== '127.0.0.1' || (int) ($settings['port'] ?? 3306) === 3306
            || ! ($settings['initialized_here'] ?? false) || realpath($settings['runtime'] ?? '') !== dirname($resolved)
            || ! preg_match('/^[a-f0-9]{12}$/D', $settings['run_id'] ?? '')
            || ($settings['database'] ?? '') !== 'aurel_itinerary_'.$settings['run_id']
            || ($settings['username'] ?? '') !== 'aurel_itin_'.$settings['run_id']
            || strlen($settings['password'] ?? '') < 32 || strlen($settings['owner_token'] ?? '') !== 64) {
            throw new \RuntimeException('Unsafe test-server identity.');
        }

        return $settings;
    }

    public static function application()
    {
        $settings = self::settings();
        $runtime = $settings['runtime'].'/itinerary-phpunit';
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        if (preg_match('/^[A-Za-z]:/', $runtime)) {
            $app->addAbsoluteCachePathPrefix(substr($runtime, 0, 2));
        }
        $app->useEnvironmentPath($runtime)->loadEnvironmentFrom('absent.env');
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($settings, $runtime) {
            $app['config']->set('database', ['default' => 'mysql', 'connections' => ['mysql' => [
                'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => $settings['port'], 'database' => $settings['database'],
                'username' => $settings['username'], 'password' => $settings['password'], 'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true, 'engine' => 'InnoDB',
            ]]]);
            $app['config']->set('view.compiled', $runtime.'/views');
            $app['config']->set('services.groq.api_key', null);
            $app['config']->set('services.geoapify.api_key', null);
            $app['config']->set('roomtype-v2.prepared_routes', false);
            $app['config']->set('roomtype-v2.production_manifest', null);
        });
        if (! is_dir($runtime.'/views')) {
            mkdir($runtime.'/views', 0777, true);
        }
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        self::guard();

        return $app;
    }

    public static function guard(): void
    {
        $settings = self::settings();
        if (! app()->environment('testing') || array_keys(config('database.connections')) !== ['mysql']) {
            throw new \RuntimeException('Only the owned test connection is allowed.');
        }
        $config = config('database.connections.mysql');
        foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
            if ($config[$key] !== $settings[$key]) {
                throw new \RuntimeException('Connection differs from owned settings.');
            }
        }
        $identity = DB::selectOne('SELECT DATABASE() AS db, @@port AS port, @@server_uuid AS uuid, @@datadir AS directory');
        if ($identity->db !== $settings['database'] || (int) $identity->port !== (int) $settings['port']
            || $identity->uuid !== $settings['server_uuid'] || realpath($identity->directory) !== realpath($settings['data'])) {
            throw new \RuntimeException('Connected server is not the owned isolated server.');
        }
        $token = DB::table('_itinerary_test_owner')->value('owner_token');
        if (! is_string($token) || ! hash_equals($settings['owner_token'], $token)) {
            throw new \RuntimeException('Missing database ownership marker.');
        }
        foreach (DB::select('SHOW GRANTS') as $row) {
            $grant = array_values((array) $row)[0];
            if (! str_starts_with($grant, 'GRANT USAGE ') && ! str_contains($grant, ' ON `'.$settings['database'].'`.* ')) {
                throw new \RuntimeException('Test user has permissions outside its owned schema.');
            }
        }
    }

    public static function schema(): void
    {
        self::guard();
        if (!Schema::hasTable('itinerary_place_exclusion')) (require base_path('database/migrations/2026_10_30_000001_create_itinerary_place_exclusions.php'))->up();
        if(Schema::hasTable('travel_source_place') && !Schema::hasColumn('travel_source_place','opening_schedule'))(require base_path('database/migrations/2026_10_31_000002_add_source_place_opening_schedule.php'))->up();
        if (Schema::hasTable('itinerary_write_request')) {
            return;
        }
        // Baseline matches the inspected signed INT / unsigned BIGINT production
        // keys, without activating unrelated catalog or booking migrations.
        Schema::create('customer', function (Blueprint $t) {
            $t->integer('id', true);
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('password_hash')->nullable();
            $t->string('role')->default('user');
        });
        (require base_path('database/migrations/2026_04_02_013436_create_location_table.php'))->up();
        (require base_path('database/migrations/2026_05_01_152156_create_attraction_table.php'))->up();
        Schema::create('hotel', function (Blueprint $t) {
            $t->integer('id', true);
            $t->integer('location_id');
            $t->string('name');
            $t->string('status')->default('active');
        });
        Schema::create('room_type', function (Blueprint $t) {
            $t->integer('id', true);
            $t->string('name');
            $t->integer('capacity');
            $t->decimal('base_price', 12, 2);
        });
        Schema::create('itinerary', function (Blueprint $t) {
            $t->integer('id', true);
            $t->integer('customer_id')->index();
            $t->integer('session_id')->nullable();
            $t->string('title', 200);
            $t->integer('location_id')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->integer('total_days')->nullable();
            $t->decimal('estimated_budget', 12, 2)->nullable();
            $t->string('status', 20)->default('draft');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('itinerary_item', function (Blueprint $t) {
            $t->integer('id', true);
            $t->integer('itinerary_id');
            $t->unsignedBigInteger('attraction_id')->nullable();
            $t->integer('day_number');
            $t->integer('order_in_day');
            $t->string('item_type', 20)->nullable();
            $t->integer('hotel_id')->nullable();
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->decimal('estimated_cost', 10, 2)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['itinerary_id', 'day_number', 'order_in_day'], 'idx_item_itinerary');
            $t->foreign('itinerary_id')->references('id')->on('itinerary')->cascadeOnDelete();
            $t->foreign('hotel_id')->references('id')->on('hotel')->nullOnDelete();
            $t->foreign('attraction_id')->references('id')->on('attraction')->nullOnDelete();
        });
        DB::table('customer')->insert([['id' => 41, 'name' => 'Synthetic owner'], ['id' => 42, 'name' => 'Synthetic other']]);
        DB::table('location')->insert([['id' => 901, 'name' => 'Hồ Chí Minh'], ['id' => 72, 'name' => 'Hà Nội']]);
        DB::table('hotel')->insert([['id' => 301, 'location_id' => 901, 'name' => 'Synthetic hotel'], ['id' => 302, 'location_id' => 72, 'name' => 'Other synthetic hotel']]);
        DB::table('room_type')->insert(['id' => 61, 'name' => 'Synthetic type', 'capacity' => 2, 'base_price' => 100]);
        DB::table('itinerary')->insert(['id' => 1, 'customer_id' => 41, 'location_id' => 901, 'title' => 'Legacy preserved', 'status' => 'draft']);
        DB::table('itinerary_item')->insert(['id' => 1, 'itinerary_id' => 1, 'day_number' => 1, 'order_in_day' => 1, 'title' => 'Legacy item preserved']);
        (require base_path('database/migrations/2026_10_23_000002_add_itinerary_trip_management.php'))->up();
    }
}
