<?php

namespace Tests\Support;

final class RoomTypeV2AcceptanceFixture
{
    public static function initialize(bool $delivery=true): \Illuminate\Database\Connection
    {
        config(['database.connections.mysql.timezone'=>'+00:00']);
        $db=RoomTypeV2FinalFixture::initialize();
        if ($delivery) (require base_path('database/migrations/2026_10_12_000008_add_booking_delivery_safety.php'))->up();
        config(['roomtype-v2.delivery_safety'=>$delivery]);
        return $db;
    }
}
