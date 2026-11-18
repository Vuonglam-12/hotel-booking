<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\Support\RoomTypeV2ReadinessFixture;
use Tests\TestCase;

class RoomTypeV2FinalMigrationTest extends TestCase
{
    public function test_targeted_migration_is_ran_on_owned_schema_preserving_legacy_history_and_signed_fks(): void
    {
        $db=RoomTypeV2ReadinessFixture::initialize();
        $db->table('booking')->insert(['id'=>901,'hotel_id'=>1,'customer_id'=>9001,'check_in'=>'2026-11-01','check_out'=>'2026-11-03','num_guests'=>2,'total_price'=>'1000000.00','status'=>'confirmed']);
        $db->table('booking_room')->insert(['id'=>1901,'booking_id'=>901,'room_type_id'=>1,'room_id'=>1,'quantity'=>1,'nights'=>2,'price_at_booking'=>'500000.00']);
        $before=$db->table('booking_room')->first();
        $this->assertSame(0,Artisan::call('migrate',['--database'=>'mysql','--path'=>'database/migrations/2026_10_12_000007_add_manual_payment_verification_audit.php','--force'=>true]),Artisan::output());
        $this->assertTrue($db->table('migrations')->where('migration','2026_10_12_000007_add_manual_payment_verification_audit')->exists());
        $this->assertEquals($before,$db->table('booking_room')->first());
        $fks=$db->select("SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME='manual_payment_verification'",[$db->getDatabaseName()]); $this->assertCount(3,$fks);
        try {
            $db->table('manual_payment_verification')->insert(['booking_id'=>901,'payment_id'=>99999,'verified_by'=>9004,'bank_reference'=>'SYNTHETIC_BAD_FK','received_amount'=>'1000000.00',
                'received_at'=>'2026-10-12 08:00:00','verified_at'=>'2026-10-12 08:00:00','evidence_source'=>'bank_statement','outcome'=>'success',
                'booking_status_before'=>'pending','booking_status_after'=>'confirmed','payment_status_before'=>'pending','payment_status_after'=>'success']);
            $this->fail('Unknown Payment FK must be rejected.');
        } catch (\Illuminate\Database\QueryException $e) { $this->assertSame(1452,$e->errorInfo[1]); }
        $this->assertSame(0,$db->table('manual_payment_verification')->count()); $this->assertSame(336,$db->table('room')->count());
    }
}
