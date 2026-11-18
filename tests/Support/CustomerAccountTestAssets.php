<?php

namespace Tests\Support;

use App\Support\RoomTypeV2Plan;

/** Private synthetic assets for fixtures; never restores or alters public demo files. */
final class CustomerAccountTestAssets
{
    public static function activate(): void
    {
        if (!app()->environment('testing')) throw new \RuntimeException('Test assets require testing.');
        $source = base_path('public');
        $root = storage_path('app/private/customer-account-phase1/test-public');
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/LuoAAAAASUVORK5CYII=');
        foreach (RoomTypeV2Plan::prepared()->groups as $group) {
            $path = $root.'/'.$group['source_image_url'];
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
            if (!is_file($path)) file_put_contents($path, $pixel);
        }
        foreach (['image/logothuonghieu.png', 'image/placeholders/accommodation.svg', 'vendor/accommodation-images.js'] as $asset) {
            if (!is_file($source.'/'.$asset)) continue;
            if (!is_dir(dirname($root.'/'.$asset))) mkdir(dirname($root.'/'.$asset), 0700, true);
            copy($source.'/'.$asset, $root.'/'.$asset);
        }
        app()->usePublicPath($root);
        config(['roomtype-v2.banking_instructions' => [
            'verified' => true, 'bank_name' => 'Synthetic Test Bank', 'bank_code' => 'TEST',
            'account_name' => 'AUREL TEST', 'account_number' => '1234567890',
            'instructions' => 'Synthetic test transfer only.', 'transfer_reference' => 'payment.transaction_id',
            'qr_payload' => 'SYNTHETIC-ONLY',
        ]]);
    }
}
