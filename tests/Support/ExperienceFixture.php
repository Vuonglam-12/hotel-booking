<?php

namespace Tests\Support;

use App\Models\Attraction;
use App\Models\Customer;
use App\Models\Location;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Schema;

final class ExperienceFixture
{
    public static function application(string $runtime, string $database = ':memory:')
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        if (preg_match('/^[A-Za-z]:/', $runtime)) $app->addAbsoluteCachePathPrefix(substr($runtime, 0, 2));
        // Do not read the project's .env or cached production configuration.
        $app->useEnvironmentPath($runtime)->loadEnvironmentFrom('absent.env');
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($runtime, $database) {
            $app['config']->set('database', ['default' => 'sqlite', 'connections' => ['sqlite' => [
                'driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => true,
            ]]]);
            $app['config']->set('view.compiled', $runtime.'/views');
            $app['config']->set('filesystems.disks.public', ['driver' => 'local', 'root' => $runtime.'/uploads', 'url' => '/storage', 'visibility' => 'public', 'throw' => false]);
            $app['config']->set('roomtype-v2.prepared_routes', false);
            $app['config']->set('roomtype-v2.production_manifest', null);
        });
        if (!is_dir($runtime.'/views')) mkdir($runtime.'/views', 0777, true);
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    public static function schema(bool $metadata = true): void
    {
        (require base_path('database/migrations/2026_04_02_013436_create_location_table.php'))->up();
        (require base_path('database/migrations/2026_05_01_152156_create_attraction_table.php'))->up();
        Schema::create('customer', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('email')->nullable(); $table->string('password_hash')->nullable(); $table->string('role')->default('user');
        });
        (require base_path('vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php'))->up();
        Schema::create('hotel', function (Blueprint $table) {
            $table->id(); $table->integer('location_id'); $table->string('name'); $table->string('address')->nullable(); $table->string('status')->default('active');
        });
        Schema::create('hotel_image', function (Blueprint $table) {
            $table->id(); $table->integer('hotel_id'); $table->string('image_url'); $table->boolean('is_primary')->default(false);
        });
        if ($metadata) self::migrate();
    }

    public static function migrate(): void
    {
        (require base_path('database/migrations/2026_10_23_000001_add_experience_catalog_metadata.php'))->up();
    }

    public static function locations(): array
    {
        $rows = [['id' => 72, 'name' => 'Hà Nội', 'code' => 'vn-ha-noi'], ['id' => 901, 'name' => 'Hồ Chí Minh', 'code' => 'vn-ho-chi-minh'],
            ['id' => 18, 'name' => 'Đà Lạt', 'code' => 'vn-da-lat'], ['id' => 405, 'name' => 'Phú Quốc', 'code' => 'vn-phu-quoc']];
        foreach ($rows as $row) { $location = new Location([...$row, 'country' => 'Việt Nam']); $location->id = $row['id']; $location->save(); }
        return $rows;
    }

    public static function published(array $override = []): Attraction
    {
        return Attraction::create([...[
            'location_id' => 72, 'item_type' => 'attraction', 'name' => 'Địa điểm kiểm thử cô lập', 'slug' => 'dia-diem-kiem-thu',
            'description' => 'Nội dung nhân tạo chỉ dùng trong môi trường kiểm thử.', 'experience_category' => 'tham-quan',
            'experience_source_url' => 'https://example.test/isolated-fixture', 'experience_location_code' => 'vn-ha-noi',
            'experience_verified_at' => now(), 'experience_verified_by' => 501, 'experience_published_at' => now(), 'is_active' => true,
        ], ...$override]);
    }

    public static function token(string $role = 'admin', string $name = 'admin_token', array $abilities = ['admin']): string
    {
        return Customer::create(['name' => 'Isolated '.$role, 'role' => $role])->createToken($name, $abilities)->plainTextToken;
    }
}
