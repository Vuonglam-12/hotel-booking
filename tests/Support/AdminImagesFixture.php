<?php

namespace Tests\Support;

use App\Services\RoomTypeAdminServiceV2;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Image-only test overlay; never enables Checkout, payment routes, or production V2. */
final class AdminImagesFixture
{
    public static function initialize(): array
    {
        $db = RoomTypeV2BackendFixture::initialize();
        Schema::table('customer', function ($table) { $table->string('role', 20)->default('user'); });
        (require base_path('vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php'))->up();
        $db->table('customer')->where('id', 9004)->update(['role' => 'admin']);
        $types = [];
        foreach ([1, 1, 2] as $index => $hotel) {
            $type = app(RoomTypeAdminServiceV2::class)->save('mysql', $hotel, null, [
                'name' => 'Image fixture ' . ($index + 1), 'capacity' => 2, 'base_price' => '500000.00',
                'total_rooms' => 5, 'status' => 'active',
            ]);
            $types[] = $type['id'];
            if ($index !== 1) $db->table('room_type_image')->insert(['room_type_id' => $type['id'], 'image_url' => 'image/img_room/room (1).jpg', 'is_primary' => true]);
        }
        return [$db, $types];
    }

    public static function routes(): void
    {
        \App\Support\RoomTypeV2TestDatabase::connection('mysql');
        // Reuse the prepared controllers and original Admin auth middleware; overlay only these routes.
        Route::middleware(['api', 'auth:admin', \App\Http\Middleware\AdminMiddleware::class])->group(function () {
            $controller = \App\Http\Controllers\PreparedV2\AdminRoomTypeController::class;
            Route::get('/api/admin/hotels/{hotel}/room-types', [$controller, 'index']);
            Route::post('/api/admin/hotels/{hotel}/room-types', [$controller, 'store']);
            Route::put('/api/admin/hotels/{hotel}/room-types/{type}', [$controller, 'update']);
            Route::delete('/api/admin/hotels/{hotel}/room-types/{type}', [$controller, 'disable']);
            Route::get('/api/admin/hotels/{hotel}/room-types/{type}/images', [$controller, 'images']);
            Route::post('/api/admin/hotels/{hotel}/room-types/{type}/images', [$controller, 'addImage']);
            Route::delete('/api/admin/hotels/{hotel}/room-types/{type}/images/{image}', [$controller, 'deleteImage']);
        });
        Route::middleware('web')->get('/admin/hotels/{hotel}/room-types', [\App\Http\Controllers\PreparedV2\AdminRoomTypeController::class, 'page']);
        Route::middleware('web')->get('/__images/prepared-hotels', [\App\Http\Controllers\PreparedV2\AdminHotelController::class, 'page']);
        Route::middleware('api')->get('/api/hotels/{hotel}/room-types', [\App\Http\Controllers\PreparedV2\RoomTypeController::class, 'index']);
        \App\Support\RoomTypeV2Integration::prioritize();
    }
}
