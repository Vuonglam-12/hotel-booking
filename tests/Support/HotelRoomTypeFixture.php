<?php

namespace Tests\Support;

use App\Support\RoomTypeV2TestDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HotelRoomTypeFixture
{
    public static function initialize(): \Illuminate\Database\Connection
    {
        $settings=RoomTypeV2TestDatabase::settings();
        $bootstrap=RoomTypeV2TestDatabase::connection('mysql');
        $schema=null;
        foreach($settings['schemas'] as $candidate) if((int)$bootstrap->selectOne('SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA=?',[$candidate])->n===0){$schema=$candidate;break;}
        if(!$schema)throw new \RuntimeException('Owned test schemas exhausted.');
        config(['database.connections.mysql.database'=>$schema]);DB::purge('mysql');
        $db=RoomTypeV2TestDatabase::connection('mysql');
        RoomTypeV2LegacyFixture::schema();
        Schema::create('destination', fn($table) => $table->integer('id',true));
        Schema::table('customer',function($table){$table->string('role',20)->default('user');$table->string('password_hash')->nullable();$table->string('avatar_url')->nullable();});
        foreach(['payment','invoice','invoice_item','hotel_image','room_availability','hotel_amenity','review','review_image'] as $table){
            (require base_path('database/migrations/2026_04_02_013436_create_'.$table.'_table.php'))->up();
            (require base_path('database/migrations/2026_04_02_013439_add_foreign_keys_to_'.$table.'_table.php'))->up();
        }
        (require base_path('vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php'))->up();
        (require base_path('database/migrations/2026_04_29_074326_add_status_to_review_table.php'))->up();
        (require base_path('database/migrations/2026_05_02_141122_create_customer_notifications_table.php'))->up();
        $db->table('location')->insert(['id'=>1,'name'=>'Test city']);
        $db->table('customer')->insert([['id'=>1,'name'=>'Admin','email'=>'admin@example.test','role'=>'admin'],['id'=>2,'name'=>'Customer','email'=>'customer@example.test','role'=>'user']]);
        foreach([1,2,3] as $id)$db->table('hotel')->insert(['id'=>$id,'name'=>'Hotel '.$id,'location_id'=>1,'latitude'=>0,'longitude'=>0]);
        $db->table('room_type')->insert(['id'=>1,'name'=>'Deluxe','capacity'=>4,'base_price'=>'800000.25','description'=>'Original shared type','bed_type'=>'King']);
        foreach([1,2] as $hotel)for($i=1;$i<=5;$i++)$db->table('room')->insert(['hotel_id'=>$hotel,'room_type_id'=>1,'room_number'=>(string)(100+$i),'price'=>'800000.25','capacity'=>4,'status'=>'available']);
        return $db;
    }

    public static function migrate(): void
    {
        (require base_path('database/migrations/2026_10_13_000010_create_hotel_room_type_catalog.php'))->up();
        (require base_path('database/migrations/2026_10_17_000002_persist_hotel_amenity_catalog.php'))->up();
        if (!Schema::hasTable('hotel_room_rate')) {
            (require base_path('database/migrations/2026_10_31_000003_create_hotel_room_rates.php'))->up();
        }
        if (!Schema::hasColumn('hotel_room_rate', 'amount_mode')) {
            (require base_path('database/migrations/2026_11_01_000001_add_explicit_room_surcharges.php'))->up();
        }
    }
}
