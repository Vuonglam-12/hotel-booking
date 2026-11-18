<?php

namespace App\Support;

final class RoomTypeV2CheckoutGate
{
    public static function staging(): bool
    {
        if (!config('roomtype-v2.staging_checkout',false)) return false;
        RoomTypeV2TestDatabase::connection('mysql');
        return true;
    }
    public static function assertWriteAllowed(): void
    {
        if (config('roomtype-v2.production_manifest')!==null) abort(403,'Production Checkout is awaiting separate acceptance and approval.');
    }
}
