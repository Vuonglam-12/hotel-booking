<?php

namespace Tests\Feature;

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaffSchemaMigrationTest extends TestCase
{
    private ?string $testDatabase = null;
    private string $primaryDatabase;
    private bool $ownsTestDatabase = false;

    protected function setUp(): void
    {
        parent::setUp();
        $primary = DB::connection('mysql')->getConfig();
        $this->primaryDatabase = $primary['database'];
        $this->testDatabase = 'aurel_staff_schema_test_' . bin2hex(random_bytes(8));
        config([
            'database.connections.staff_schema_test_server' => array_replace($primary, [
                'name' => 'staff_schema_test_server', 'url' => null, 'database' => 'information_schema', 'prefix' => '',
            ]),
            'database.connections.staff_schema_fixture' => array_replace($primary, [
                'name' => 'staff_schema_fixture', 'url' => null, 'database' => $this->testDatabase, 'prefix' => '',
            ]),
            'database.default' => 'staff_schema_fixture',
        ]);

        // The migrator switches defaults using Connection::getName(). Block the primary
        // alias entirely in this process so a naming regression can never reach its PDO.
        DB::purge('mysql');
        config(['database.connections.mysql.driver' => 'staff_schema_primary_forbidden']);
        DB::extend('staff_schema_primary_forbidden', static function () {
            throw new \LogicException('The primary database is disabled in Staff schema migration tests.');
        });

        try {
            $server = DB::connection('staff_schema_test_server');
            $this->assertFalse($server->table('SCHEMATA')->where('SCHEMA_NAME', $this->testDatabase)->exists());
            $server->getSchemaBuilder()->createDatabase($this->testDatabase);
            $this->ownsTestDatabase = true;
            $this->assertSafeFixtureConnection();
            Schema::clearResolvedInstance('db.schema');
            $this->createFixtures();
        } catch (\Throwable $error) {
            $this->removeOwnedTestDatabase();
            throw $error;
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->removeOwnedTestDatabase();
        } finally {
            parent::tearDown();
        }
    }

    private function assertSafeFixtureConnection(): void
    {
        $this->assertMatchesRegularExpression('/^aurel_staff_schema_test_[a-f0-9]{16}$/', $this->testDatabase);
        $this->assertNotSame($this->primaryDatabase, $this->testDatabase);
        $this->assertSame($this->testDatabase, DB::connection()->getDatabaseName());
        $this->assertSame('staff_schema_fixture', DB::connection()->getName());
        $this->assertSame('staff_schema_fixture', DB::getDefaultConnection());
        $this->assertSame('mysql', DB::connection()->getDriverName());
    }

    private function removeOwnedTestDatabase(): void
    {
        if (!$this->ownsTestDatabase) return;
        if (!preg_match('/^aurel_staff_schema_test_[a-f0-9]{16}$/', $this->testDatabase)
            || $this->testDatabase === $this->primaryDatabase) {
            throw new \LogicException('Refusing to remove a database not owned by this test.');
        }
        DB::purge('staff_schema_fixture');
        DB::connection('staff_schema_test_server')->getSchemaBuilder()->dropDatabaseIfExists($this->testDatabase);
        $this->ownsTestDatabase = false;
        DB::purge('staff_schema_test_server');
    }

    private function fixtureConnection(): Connection
    {
        $this->assertSafeFixtureConnection();
        return DB::connection('staff_schema_fixture');
    }

    private function createFixtures(): void
    {
        $connection = $this->fixtureConnection();
        $schema = $connection->getSchemaBuilder();
        foreach (['customer', 'booking', 'hotel', 'room', 'payment', 'personal_access_tokens'] as $name) {
            $schema->create($name, function (Blueprint $table) use ($name) {
                $table->engine = 'InnoDB';
                $table->integer('id', true);
                $table->string('fixture_value');
                if ($name === 'personal_access_tokens') $table->string('tokenable_type');
            });
            $connection->table($name)->insert(array_merge(['id' => 1, 'fixture_value' => $name . '-fixture'],
                $name === 'personal_access_tokens' ? ['tokenable_type' => 'App\\Models\\Customer'] : []));
        }
        // Execute the two original migrations only in this newly created, disposable database.
        (require database_path('migrations/2026_04_02_013436_create_staff_table.php'))->up();
        (require database_path('migrations/2026_04_02_013439_add_foreign_keys_to_staff_table.php'))->up();
    }

    private function migration(): Migration
    {
        $this->assertSafeFixtureConnection();
        return require database_path('migrations/2026_10_11_000001_drop_unused_staff_table.php');
    }

    private function protectedData(): array
    {
        $connection = $this->fixtureConnection();
        $result = [];
        foreach (['customer', 'booking', 'hotel', 'room', 'payment', 'personal_access_tokens'] as $table) {
            $result[$table] = $connection->table($table)->orderBy('id')->get()->toArray();
        }
        return $result;
    }

    private function staffDefinition(): string
    {
        return $this->fixtureConnection()->selectOne('SHOW CREATE TABLE staff')->{'Create Table'};
    }

    private function assertUpIsBlocked(string $message): void
    {
        $schema = $this->staffDefinition();
        $protected = $this->protectedData();
        try {
            $this->migration()->up();
            $this->fail('The migration should reject this dependency before running DDL.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString($message, $error->getMessage());
        }
        $this->assertSame($schema, $this->staffDefinition());
        $this->assertEquals($protected, $this->protectedData());
    }

    public function test_up_removes_only_staff_and_down_restores_the_exact_empty_historical_schema(): void
    {
        $connection = $this->fixtureConnection();
        $schema = $this->staffDefinition();
        $protected = $this->protectedData();
        $this->migration()->up();
        $this->assertFalse($connection->getSchemaBuilder()->hasTable('staff'));
        $this->assertSame(0, $connection->table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $this->testDatabase)->where('CONSTRAINT_NAME', 'fk_staff_hotel')->count());
        $this->assertEquals($protected, $this->protectedData());
        $this->migration()->down();
        $this->assertSame($schema, $this->staffDefinition());
        $this->assertSame(0, $connection->table('staff')->count());
        $this->assertEquals($protected, $this->protectedData());
    }

    public function test_primary_database_connection_is_inaccessible_to_this_test_process(): void
    {
        try {
            DB::connection('mysql')->getPdo();
            $this->fail('The primary connection must not be accessible to migration tests.');
        } catch (\LogicException $error) {
            $this->assertStringContainsString('primary database is disabled', $error->getMessage());
        }
        $this->assertSafeFixtureConnection();
    }

    public function test_laravel_migrator_applies_and_rolls_back_only_the_new_migration_on_the_fixture_database(): void
    {
        $this->assertSafeFixtureConnection();
        $schema = $this->staffDefinition();
        $protected = $this->protectedData();
        $options = [
            '--database' => 'staff_schema_fixture',
            '--path' => 'database/migrations/2026_10_11_000001_drop_unused_staff_table.php',
            '--force' => true,
        ];
        $this->withoutMockingConsoleOutput();
        $this->assertSame(0, $this->artisan('migrate', $options));
        $this->assertFalse($this->fixtureConnection()->getSchemaBuilder()->hasTable('staff'),
            $this->app[\Illuminate\Contracts\Console\Kernel::class]->output());
        $this->assertSame(1, $this->fixtureConnection()->table('migrations')
            ->where('migration', '2026_10_11_000001_drop_unused_staff_table')->count());
        $this->assertEquals($protected, $this->protectedData());
        $this->assertSame(0, $this->artisan('migrate:rollback', array_merge($options, ['--step' => 1])));
        $this->assertSame($schema, $this->staffDefinition());
        $this->assertSame(0, $this->fixtureConnection()->table('staff')->count());
        $this->assertEquals($protected, $this->protectedData());
    }

    public function test_staff_records_block_drop_and_are_preserved(): void
    {
        $this->fixtureConnection()->table('staff')->insert([
            'name' => 'Test fixture', 'email' => 'staff-fixture@example.invalid',
            'password_hash' => 'fixture-only', 'role' => 'staff', 'hotel_id' => 1,
        ]);
        $this->assertUpIsBlocked('contains records');
        $this->assertSame(1, $this->fixtureConnection()->table('staff')->count());
    }

    #[DataProvider('staffTokenTypes')]
    public function test_staff_tokens_block_drop_and_are_preserved(string $type): void
    {
        $this->fixtureConnection()->table('personal_access_tokens')->insert([
            'id' => 2, 'fixture_value' => 'legacy-token-fixture', 'tokenable_type' => $type,
        ]);
        $this->assertUpIsBlocked('Staff tokens');
    }

    public static function staffTokenTypes(): array
    {
        return [['App\\Models\\Staff'], ['Staff'], ['staff']];
    }

    public function test_incoming_foreign_key_blocks_drop_even_when_staff_is_empty(): void
    {
        $this->fixtureConnection()->getSchemaBuilder()->create('fixture_dependency', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('staff_id');
            $table->foreign('staff_id')->references('id')->on('staff');
        });
        $this->assertUpIsBlocked('incoming foreign key');
    }

    public function test_unexpected_outgoing_foreign_key_blocks_drop(): void
    {
        $this->fixtureConnection()->getSchemaBuilder()->table('staff', function (Blueprint $table) {
            $table->foreign('id', 'fixture_unexpected_fk')->references('id')->on('customer');
        });
        $this->assertUpIsBlocked('unexpected outgoing foreign key');
    }

    public function test_staff_id_on_another_table_blocks_drop_without_a_foreign_key(): void
    {
        $this->fixtureConnection()->getSchemaBuilder()->create('fixture_logical_dependency', function (Blueprint $table) {
            $table->integer('staff_id')->nullable();
        });
        $this->assertUpIsBlocked('logical Staff ID');
    }

    public function test_view_dependency_blocks_drop(): void
    {
        $this->fixtureConnection()->statement('CREATE VIEW fixture_staff_view AS SELECT id FROM staff');
        $this->assertUpIsBlocked('VIEWS');
    }

    public function test_routine_dependency_blocks_drop(): void
    {
        $this->fixtureConnection()->statement('CREATE PROCEDURE fixture_staff_routine() SELECT COUNT(*) FROM staff');
        $this->assertUpIsBlocked('ROUTINES');
    }

    public function test_event_dependency_blocks_drop(): void
    {
        $this->fixtureConnection()->statement('CREATE EVENT fixture_staff_event ON SCHEDULE AT CURRENT_TIMESTAMP + INTERVAL 1 DAY DO SELECT COUNT(*) INTO @fixture_event_count FROM staff');
        $this->assertUpIsBlocked('EVENTS');
    }

    public function test_trigger_on_staff_blocks_drop_without_a_staff_reference_in_its_body(): void
    {
        $this->fixtureConnection()->statement('CREATE TRIGGER fixture_actor_hook BEFORE INSERT ON staff FOR EACH ROW SET @fixture_marker = 1');
        $this->assertUpIsBlocked('trigger');
    }

    public function test_up_is_safe_to_repeat_if_staff_is_already_absent(): void
    {
        $protected = $this->protectedData();
        $this->migration()->up();
        $this->migration()->up();
        $this->assertEquals($protected, $this->protectedData());
    }

    public function test_down_does_not_overwrite_an_existing_staff_table(): void
    {
        $this->fixtureConnection()->table('staff')->insert([
            'name' => 'Test fixture', 'email' => 'staff-fixture@example.invalid', 'password_hash' => 'fixture-only',
        ]);
        $schema = $this->staffDefinition();
        $this->migration()->down();
        $this->assertSame($schema, $this->staffDefinition());
        $this->assertSame(1, $this->fixtureConnection()->table('staff')->count());
    }

    public function test_down_without_hotel_does_not_create_a_partial_staff_table(): void
    {
        $this->migration()->up();
        $this->fixtureConnection()->getSchemaBuilder()->drop('hotel');
        try {
            $this->migration()->down();
            $this->fail('Rollback must reject the missing Hotel dependency.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('hotel.id', $error->getMessage());
        }
        $this->assertFalse($this->fixtureConnection()->getSchemaBuilder()->hasTable('staff'));
    }

    public function test_down_with_incompatible_hotel_id_does_not_create_a_partial_staff_table(): void
    {
        $this->migration()->up();
        $this->fixtureConnection()->statement('ALTER TABLE hotel MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        try {
            $this->migration()->down();
            $this->fail('Rollback must reject incompatible Hotel keys.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('signed INT', $error->getMessage());
        }
        $this->assertFalse($this->fixtureConnection()->getSchemaBuilder()->hasTable('staff'));
    }

    public function test_disabled_foreign_key_checks_block_the_migration(): void
    {
        $connection = $this->fixtureConnection();
        $connection->statement('SET SESSION foreign_key_checks = 0');
        try {
            $this->assertUpIsBlocked('Foreign key checks');
        } finally {
            $connection->statement('SET SESSION foreign_key_checks = 1');
        }
    }
}
