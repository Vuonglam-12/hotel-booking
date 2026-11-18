<?php

namespace App\Support;

use App\Http\Controllers\PreparedV2\DemoBookingController;
use Illuminate\Support\Facades\Route;

final class BookingDemo
{
    public static function connection(): \Illuminate\Database\Connection
    {
        abort_unless(config('booking-demo.enabled') === true && app()->environment('testing')
            && config('roomtype-v2.production_manifest') === null, 404);
        return RoomTypeV2TestDatabase::connection('mysql');
    }

    public static function activate(): void
    {
        self::connection();
        config(['roomtype-v2.banking_instructions'=>null,'services.vnpay'=>[],
            'services.sepay.webhook.enabled'=>false,'mail.default'=>'array']);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        app(\Illuminate\Contracts\Http\Kernel::class)->prependMiddleware(\App\Http\Middleware\BookingDemoSafety::class);
        Route::middleware(['api','auth:sanctum'])->group(function () {
            Route::post('/api/bookings',[DemoBookingController::class,'store']);
            Route::get('/api/demo/bookings/{id}',[DemoBookingController::class,'detail'])->whereNumber('id');
            Route::post('/api/demo/bookings/{id}/simulate',[DemoBookingController::class,'simulate'])->whereNumber('id');
            Route::post('/api/demo/bookings/{id}/retry',[DemoBookingController::class,'retry'])->whereNumber('id');
        });
        Route::middleware(['api','auth:admin',\App\Http\Middleware\AdminMiddleware::class])->group(function () {
            Route::get('/api/admin/demo/bookings',[DemoBookingController::class,'listing']);
            Route::get('/api/admin/demo/bookings/{id}',[DemoBookingController::class,'adminDetail'])->whereNumber('id');
            Route::post('/api/admin/demo/bookings/{id}/simulate',[DemoBookingController::class,'adminSimulate'])->whereNumber('id');
        });
        Route::middleware('web')->group(function () {
            Route::get('/payments/bookings/{booking}',[DemoBookingController::class,'page'])->whereNumber('booking');
            Route::get('/admin/bookings',[DemoBookingController::class,'adminPage'])->name('admin.bookings');
            Route::get('/demo/admin/payments/{id}',[DemoBookingController::class,'adminPaymentPage'])->whereNumber('id');
            Route::view('/demo','demo.welcome');
        });
        RoomTypeV2Integration::prioritize();
    }
}
