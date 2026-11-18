<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $db = DB::connection($this->getConnection());
        if ($db->getDriverName() !== 'mysql') throw new RuntimeException('This migration requires MySQL 8.');
        if ($db->table('booking')->whereIn('status', ['pending','confirmed'])
            ->select('customer_id','hotel_id','check_in','check_out')->groupBy('customer_id','hotel_id','check_in','check_out')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Conflicting active Booking history; resolve explicitly before migration.');
        }
        // One atomic MySQL ALTER: the old constraint is never removed without its replacement.
        $db->statement("ALTER TABLE booking DROP INDEX uq_booking_prevent_duplicate,
            ADD COLUMN active_duplicate_key TINYINT GENERATED ALWAYS AS (CASE WHEN status IN ('pending','confirmed') THEN 1 ELSE NULL END) STORED,
            ADD UNIQUE INDEX uq_booking_active (customer_id,hotel_id,check_in,check_out,active_duplicate_key)");
    }

    public function down(): void
    {
        $db = DB::connection($this->getConnection());
        if ($db->table('booking')->select('customer_id','hotel_id','check_in','check_out','status')
            ->groupBy('customer_id','hotel_id','check_in','check_out','status')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Rollback refused: legacy status unique cannot represent this history.');
        }
        $db->statement('ALTER TABLE booking DROP INDEX uq_booking_active, DROP COLUMN active_duplicate_key,
            ADD UNIQUE INDEX uq_booking_prevent_duplicate (customer_id,hotel_id,check_in,check_out,status)');
    }
};
