<?php

namespace Tests\Support;

use App\Support\RoomTypeV2Plan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class RoomTypeV2ReadinessFixture
{
    private static ?string $imageDirectory = null;

    public static function plan(): RoomTypeV2Plan
    {
        $mapping = fopen(base_path('docs/hotel-roomtype-phase2a-mapping.csv'), 'rb');
        if (!$mapping) throw new \RuntimeException('Cannot read the prepared RoomType mapping for the isolated fixture.');
        $disk = Storage::disk('public');
        self::$imageDirectory = 'aurel-roomtype-v2-fixtures/' . bin2hex(random_bytes(8));
        $imagePath = self::$imageDirectory . '/room.png';
        $temporary = tempnam(sys_get_temp_dir(), 'aurel-roomtype-v2-');
        if ($temporary === false) {
            fclose($mapping);
            self::cleanup();
            throw new \RuntimeException('Cannot create the isolated RoomType fixture mapping.');
        }

        try {
            if (!$disk->makeDirectory(self::$imageDirectory)) {
                throw new \RuntimeException('Cannot create the isolated RoomType fixture image directory.');
            }
            $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/LuoAAAAASUVORK5CYII=', true);
            if ($image === false || !$disk->put($imagePath, $image)) {
                throw new \RuntimeException('Cannot write the isolated RoomType fixture image.');
            }

            $output = fopen($temporary, 'wb');
            if (!$output) throw new \RuntimeException('Cannot write the isolated RoomType fixture mapping.');
            try {
                $headers = fgetcsv($mapping);
                $imageColumn = is_array($headers) ? array_search('source_image_url', $headers, true) : false;
                if ($imageColumn === false) {
                    throw new \RuntimeException('Prepared RoomType mapping has no source image column.');
                }
                fputcsv($output, $headers);
                while (($row = fgetcsv($mapping)) !== false) {
                    $row[$imageColumn] = 'storage/' . $imagePath;
                    fputcsv($output, $row);
                }
            } finally {
                fclose($output);
            }

            return new RoomTypeV2Plan($temporary, base_path('docs/hotel-roomtype-phase2b1-source-types.json'));
        } catch (\Throwable $exception) {
            self::cleanup();
            throw $exception;
        } finally {
            fclose($mapping);
            unlink($temporary);
        }
    }

    public static function cleanup(): void
    {
        if (self::$imageDirectory === null) return;
        $directory = self::$imageDirectory;
        self::$imageDirectory = null;
        if (!Storage::disk('public')->deleteDirectory($directory)) {
            throw new \RuntimeException('Cannot remove the isolated RoomType fixture image directory.');
        }
    }

    public static function initialize(?RoomTypeV2Plan $plan = null, bool $keepImageAsset = false): \Illuminate\Database\Connection
    {
        $plan ??= self::plan();
        try {
            $db=RoomTypeV2BackendFixture::initialize($plan);
            foreach (['000005_replace_booking_status_unique','000006_create_payment_receipts_and_effects'] as $name) {
                (require base_path('database/migrations/2026_10_12_'.$name.'.php'))->up();
            }
            (require base_path('database/migrations/2026_10_19_000001_create_payment_gateway_unmatched_events.php'))->up();
            Schema::table('customer',function ($t) {
                $t->string('role',20)->default('user'); $t->string('password_hash',255)->nullable(); $t->string('phone',20)->nullable(); $t->string('avatar_url',500)->nullable();
            });
            (require base_path('vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php'))->up();
            config(['services.vnpay'=>['tmn_code'=>'TEST0001','hash_secret'=>str_repeat('synthetic-test-secret-',3),
                'url'=>'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html','return_url'=>'http://roomtype-v2-test.invalid/payment/result']]);
            return $db;
        } finally {
            if (!$keepImageAsset) self::cleanup();
        }
    }
}
