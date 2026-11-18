<?php

namespace Tests\Support;

use App\Services\RoomTypeV2BackfillService;
use App\Services\RoomTypeV2RemapService;
use App\Support\RoomTypeV2Plan;

final class RoomTypeV2FinalFixture
{
    public static function initialize(): \Illuminate\Database\Connection
    {
        CustomerAccountTestAssets::activate();
        $db=RoomTypeV2ReadinessFixture::initialize(RoomTypeV2Plan::prepared()); RoomTypeV2CutoverFixture::presentation();
        (require base_path('database/migrations/2026_10_12_000007_add_manual_payment_verification_audit.php'))->up();
        app(RoomTypeV2BackfillService::class)->run('mysql',RoomTypeV2Plan::prepared());
        $manifest=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'))).'/remap-'.bin2hex(random_bytes(6)).'.json';
        app(RoomTypeV2RemapService::class)->prepare('mysql',RoomTypeV2Plan::prepared(),$manifest); app(RoomTypeV2RemapService::class)->run('mysql',$manifest);
        $db->table('customer')->where('id',9004)->update(['role'=>'admin']);
        return $db;
    }
}
