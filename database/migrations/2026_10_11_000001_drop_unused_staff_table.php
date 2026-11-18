<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = $this->mysqlConnection();
        $schema = $connection->getSchemaBuilder();
        $database = $connection->getDatabaseName();
        $staffTable = $connection->getTablePrefix() . 'staff';
        $hotelTable = $connection->getTablePrefix() . 'hotel';

        if ($schema->hasTable('staff') && $connection->table('staff')->exists()) {
            throw new RuntimeException('Refusing to drop staff: the table contains records.');
        }

        if ($schema->hasTable('personal_access_tokens') && $connection->table('personal_access_tokens')
            ->whereIn('tokenable_type', ['App\\Models\\Staff', 'Staff', 'staff'])->exists()) {
            throw new RuntimeException('Refusing to drop staff: Staff tokens still exist.');
        }

        // Include references from other schemas; never drop a foreign key on another table.
        if ($connection->table('information_schema.KEY_COLUMN_USAGE')
            ->where('REFERENCED_TABLE_SCHEMA', $database)->where('REFERENCED_TABLE_NAME', $staffTable)->exists()) {
            throw new RuntimeException('Refusing to drop staff: incoming foreign key dependencies exist.');
        }

        if ($connection->table('information_schema.COLUMNS')->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', '!=', $staffTable)->where('COLUMN_NAME', 'REGEXP', '(^|_)staff_id$')->exists()) {
            throw new RuntimeException('Refusing to drop staff: logical Staff ID dependencies exist on another table.');
        }

        $this->assertNoSqlDependencies($connection, $database, $staffTable);

        $foreignKeys = $connection->table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)->where('TABLE_NAME', $staffTable)
            ->whereNotNull('REFERENCED_TABLE_NAME')->get();
        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey->CONSTRAINT_NAME !== 'fk_staff_hotel'
                || $foreignKey->COLUMN_NAME !== 'hotel_id'
                || $foreignKey->REFERENCED_TABLE_SCHEMA !== $database
                || $foreignKey->REFERENCED_TABLE_NAME !== $hotelTable
                || $foreignKey->REFERENCED_COLUMN_NAME !== 'id') {
                throw new RuntimeException('Refusing to drop staff: an unexpected outgoing foreign key exists.');
            }
        }

        if (!$schema->hasTable('staff')) {
            return;
        }

        // The outgoing FK belongs to staff and is removed with the table in this single DDL.
        // Foreign key checks stay enabled; hotel and all other tables are left intact.
        $schema->drop('staff');
    }

    public function down(): void
    {
        $connection = $this->mysqlConnection();
        $schema = $connection->getSchemaBuilder();
        if ($schema->hasTable('staff')) {
            return; // Never overwrite an existing table or its data during rollback.
        }

        $database = $connection->getDatabaseName();
        $hotelTable = $connection->getTablePrefix() . 'hotel';
        $hotel = $connection->table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $database)->where('TABLE_NAME', $hotelTable)->first(['ENGINE']);
        $hotelId = $connection->table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $database)->where('TABLE_NAME', $hotelTable)
            ->where('COLUMN_NAME', 'id')->first(['DATA_TYPE', 'COLUMN_TYPE', 'COLUMN_KEY']);
        if (!$hotel || $hotel->ENGINE !== 'InnoDB' || !$hotelId || $hotelId->DATA_TYPE !== 'int'
            || str_contains(strtolower($hotelId->COLUMN_TYPE), 'unsigned') || $hotelId->COLUMN_KEY !== 'PRI') {
            throw new RuntimeException('Cannot restore staff: hotel.id must be a signed INT primary key on an InnoDB table.');
        }

        // Restore only the historical structure; no accounts or tokens are recreated.
        $schema->create('staff', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->comment('Nhân viên / Admin');
            $table->integer('id', true);
            $table->integer('hotel_id')->nullable()->index('idx_staff_hotel')->comment('NULL = superadmin toàn hệ thống');
            $table->string('name', 100);
            $table->string('email', 100)->unique('uq_staff_email');
            $table->string('phone', 20)->nullable();
            $table->string('password_hash');
            $table->string('role', 30)->default('staff')->comment('superadmin/admin/manager/staff');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('hotel_id', 'fk_staff_hotel')->references('id')->on('hotel')
                ->onUpdate('no action')->onDelete('set null');
        });
    }

    private function mysqlConnection(): Connection
    {
        $connection = DB::connection($this->getConnection());
        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('This migration requires the MySQL connection used by the historical Staff schema.');
        }
        if ((int) $connection->selectOne('SELECT @@SESSION.foreign_key_checks AS enabled')->enabled !== 1) {
            throw new RuntimeException('Foreign key checks must remain enabled for the Staff migration.');
        }

        return $connection;
    }

    private function assertNoSqlDependencies(Connection $connection, string $database, string $staffTable): void
    {
        $pattern = '(^|[^[:alnum:]_])' . preg_quote($staffTable) . '([^[:alnum:]_]|$)';
        foreach ([
            'VIEWS' => ['TABLE_SCHEMA', 'VIEW_DEFINITION'],
            'ROUTINES' => ['ROUTINE_SCHEMA', 'ROUTINE_DEFINITION'],
            'EVENTS' => ['EVENT_SCHEMA', 'EVENT_DEFINITION'],
        ] as $catalog => [$schemaColumn, $definitionColumn]) {
            if ($connection->table('information_schema.' . $catalog)->where($schemaColumn, $database)
                ->where(function ($query) use ($definitionColumn, $pattern) {
                    $query->where($definitionColumn, 'REGEXP', $pattern)->orWhereNull($definitionColumn);
                })->exists()) {
                throw new RuntimeException('Refusing to drop staff: SQL dependencies or unreadable definitions exist in ' . $catalog . '.');
            }
        }

        if ($connection->table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', $database)
            ->where(function ($query) use ($staffTable, $pattern) {
                $query->where('EVENT_OBJECT_TABLE', $staffTable)->orWhere('ACTION_STATEMENT', 'REGEXP', $pattern);
            })->exists()) {
            throw new RuntimeException('Refusing to drop staff: trigger dependencies exist.');
        }
    }
};
