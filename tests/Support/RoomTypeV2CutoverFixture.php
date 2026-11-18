<?php

namespace Tests\Support;

use App\Support\RoomTypeV2Plan;
use App\Support\RoomTypeV2TestDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Builds legacy input from SOURCE only; does not inspect the application database. */
final class RoomTypeV2CutoverFixture
{
    public static function initialize(): \Illuminate\Database\Connection
    {
        $settings=RoomTypeV2TestDatabase::settings();
        $db=RoomTypeV2TestDatabase::connection('mysql'); $schema=null;
        foreach ($settings['schemas'] as $candidate) {
            if ((int)$db->selectOne('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=?',[$candidate])->n===0) { $schema=$candidate; break; }
        }
        if (!$schema) throw new \RuntimeException('Owned test schemas exhausted.');
        config(['database.connections.mysql.database'=>$schema]); DB::purge('mysql');
        $db=RoomTypeV2TestDatabase::connection('mysql');
        RoomTypeV2LegacyFixture::schema();
        foreach (['payment','invoice','invoice_item','hotel_image','room_availability','hotel_amenity'] as $table) {
            (require base_path('database/migrations/2026_04_02_013436_create_'.$table.'_table.php'))->up();
        }
        foreach (['payment','invoice','invoice_item','hotel_image','room_availability','hotel_amenity'] as $table) {
            (require base_path('database/migrations/2026_04_02_013439_add_foreign_keys_to_'.$table.'_table.php'))->up();
        }
        (require base_path('database/migrations/2026_05_02_141122_create_customer_notifications_table.php'))->up();
        RoomTypeV2LegacyFixture::data($db,RoomTypeV2Plan::prepared());
        RoomTypeV2LegacyFixture::history($db);
        foreach ([9002,9003,9004] as $id) $db->table('customer')->insert(['id'=>$id,'name'=>'Isolated integration fixture','email'=>'fixture-'.$id.'@example.test']);
        self::presentation();
        Schema::table('customer',function ($t) {
            $t->string('role',20)->default('user'); $t->string('password_hash',255)->nullable(); $t->string('phone',20)->nullable(); $t->string('avatar_url',500)->nullable();
        });
        (require base_path('vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php'))->up();
        self::protocol();
        return $db;
    }

    public static function presentation(): void
    {
        Schema::table('customer',fn($t)=>$t->timestamp('created_at')->useCurrent());
        Schema::create('destination',fn($t)=>$t->integer('id',true));
        foreach (['review','review_image'] as $table) (require base_path('database/migrations/2026_04_02_013436_create_'.$table.'_table.php'))->up();
        foreach (['review','review_image'] as $table) (require base_path('database/migrations/2026_04_02_013439_add_foreign_keys_to_'.$table.'_table.php'))->up();
        (require base_path('database/migrations/2026_04_29_074326_add_status_to_review_table.php'))->up();
    }

    public static function protocol(): void
    {
        config(['services.vnpay'=>['tmn_code'=>'TEST0001','hash_secret'=>str_repeat('synthetic-test-secret-',3),
            'url'=>'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html','return_url'=>'http://roomtype-v2-test.invalid/payment/result']]);
    }
}
