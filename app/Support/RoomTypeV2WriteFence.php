<?php

namespace App\Support;

use Illuminate\Database\Connection;

/** Application-level defense; direct PDO and old/out-of-app processes require operational controls. */
final class RoomTypeV2WriteFence
{
    private static ?\WeakMap $installed = null;
    private const WRITERS = [
        \App\Services\ManualBankingPaymentServiceV2::class,
        \App\Services\SePayWebhookServiceV2::class,
        \App\Services\BookingServiceV2::class,
        \App\Services\BookingLifecycleServiceV2::class,
        \App\Services\VnpayPaymentServiceV2::class,
        \App\Services\BookingEffectServiceV2::class,
        \App\Services\RoomTypeAdminServiceV2::class,
        \App\Services\RoomTypeInventoryServiceV2::class,
    ];

    public static function install(Connection $db): void
    {
        RoomTypeV2Database::connection($db->getName());
        self::$installed ??= new \WeakMap();
        if (isset(self::$installed[$db])) return;
        self::$installed[$db] = true;
        $db->beforeExecuting(function (string $sql) {
            $sql = preg_replace('~/\*.*?\*/~s', '', $sql);
            if (!preg_match('/^\s*(insert(?:\s+ignore)?\s+into|replace\s+into|update|delete\s+from)\s+[`"]?(booking|booking_room|payment|invoice|invoice_item|payment_gateway_receipt|payment_gateway_unmatched_event|booking_effect|room|room_image|room_type|room_type_image|room_type_legacy_map|manual_payment_verification|booking_state_audit|booking_effect_notification|booking_delivery_audit)[`"]?(?=\s|\()/i', $sql)) return;
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
                if (in_array($frame['class'] ?? '', self::WRITERS, true)) return;
                if (($frame['class']??'')===\App\Services\DemoBookingPaymentService::class && config('booking-demo.enabled')===true && app()->environment('testing')) return;
            }
            throw new \RuntimeException('Legacy or unregistered inventory/payment writer refused by the V2 test cutover fence.');
        });
    }
}
