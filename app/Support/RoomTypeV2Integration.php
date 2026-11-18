<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PreparedV2\PaymentController;

/** Explicit guarded registrar; production provider remains unregistered until separately approved. */
final class RoomTypeV2Integration
{
    public static function payments(): void
    {
        RoomTypeV2Database::connection('mysql'); // Validate owned identity before changing the route collection.
        $kernel=app(\Illuminate\Contracts\Http\Kernel::class);
        // route:list flushes the router's groups; restore the application's existing groups for subsequent tests.
        foreach ($kernel->getMiddlewareGroups() as $name=>$middlewares) app('router')->middlewareGroup($name,$middlewares);
        Route::middleware([\Illuminate\Routing\Middleware\ThrottleRequests::class.':api', \Illuminate\Routing\Middleware\SubstituteBindings::class])->group(function () {
            Route::get('/api/payments/vnpay-return',[PaymentController::class,'vnpayReturn']);
            Route::get('/api/payments/vnpay-ipn',[PaymentController::class,'ipn']);
            Route::get('/payment/result',[PaymentController::class,'vnpayReturn']);
            Route::middleware('auth:sanctum')->group(function () {
                Route::post('/api/payments/create',[PaymentController::class,'createPayment']);
                Route::post('/api/payments/retry',[PaymentController::class,'retry']);
                Route::post('/api/payments/manual',[PaymentController::class,'createManual']);
            });
        });
        self::prioritize();
    }

    public static function prioritize(): void
    {
        RoomTypeV2Database::connection('mysql');
        $routes=app('router')->getRoutes()->getRoutes();
        $collection=new \Illuminate\Routing\RouteCollection();
        $seen=[];
        foreach ([true,false] as $prepared) foreach ($routes as $route) {
            if (str_contains($route->getActionName(),'PreparedV2\\')===$prepared) {
                if ($prepared) $route->whereNumber(['hotel','type','image','id']);
                $key=implode('|',$route->methods()).' '.$route->getDomain().' '.preg_replace('/\{[^}]+\}/','{}',$route->uri());
                if (isset($seen[$key])) continue;
                $seen[$key]=true;
                $collection->add($route);
            }
        }
        app('router')->setRoutes($collection);
        $collection->refreshNameLookups(); $collection->refreshActionLookups();
    }

    public static function backend(): void
    {
        self::payments();
        Route::middleware('api')->group(function () {
            Route::get('/api/hotels/{hotel}/room-types',[\App\Http\Controllers\PreparedV2\RoomTypeController::class,'index'])->whereNumber('hotel');
            Route::middleware('auth:sanctum')->group(function () {
                $c=\App\Http\Controllers\PreparedV2\BookingController::class;
                Route::post('/api/bookings',[$c,'store']); Route::post('/api/bookings/{id}/cancel',[$c,'cancel']);
                Route::get('/api/bookings/my',[$c,'mine']); Route::get('/api/bookings/{id}',[$c,'show'])->whereNumber('id');
            });
            Route::middleware(['auth:admin',\App\Http\Middleware\AdminMiddleware::class])->group(function () {
                Route::put('/api/admin/bookings/{id}/status',[\App\Http\Controllers\PreparedV2\BookingController::class,'status']);
                $c=\App\Http\Controllers\PreparedV2\AdminRoomTypeController::class;
                Route::get('/api/admin/hotels/{hotel}/room-types',[$c,'index']); Route::post('/api/admin/hotels/{hotel}/room-types',[$c,'store']);
                Route::put('/api/admin/hotels/{hotel}/room-types/{type}',[$c,'update']); Route::delete('/api/admin/hotels/{hotel}/room-types/{type}',[$c,'disable']);
                Route::get('/api/admin/hotels/{hotel}/room-types/{type}/images',[$c,'images']); Route::post('/api/admin/hotels/{hotel}/room-types/{type}/images',[$c,'addImage']);
                Route::delete('/api/admin/hotels/{hotel}/room-types/{type}/images/{image}',[$c,'deleteImage']);
            });
        });
        self::prioritize();
    }

    public static function complete(): void
    {
        $db=RoomTypeV2Database::connection('mysql');
        foreach (['room_type_image','room_type_legacy_map','payment_gateway_receipt','payment_gateway_unmatched_event','booking_effect'] as $table) {
            if (!$db->getSchemaBuilder()->hasTable($table)) throw new \RuntimeException('Complete integration requires all migrations.');
        }
        if (config('services.sepay.webhook.enabled', false) && !$db->getSchemaBuilder()->hasTable('sepay_webhook_event')) {
            throw new \RuntimeException('Enabled SePay integration requires its event-audit migration.');
        }
        $expectedMappings=config('roomtype-v2.production_manifest')===null?105:RoomTypeV2ProductionGuard::manifest()['mapping_count'];
        if (!$db->getSchemaBuilder()->hasColumn('booking','active_duplicate_key') || $db->table('room_type_legacy_map')->count()!==$expectedMappings) {
            throw new \RuntimeException('Complete integration requires the active constraint and 105 backfill mappings.');
        }
        $badRooms=$db->table('room as r')->leftJoin('room_type as rt','rt.id','=','r.room_type_id')
            ->where(fn($q)=>$q->whereNull('rt.hotel_id')->orWhereColumn('rt.hotel_id','!=','r.hotel_id'))->exists();
        $badHistory=$db->table('booking_room as br')->join('booking as b','b.id','=','br.booking_id')->leftJoin('room_type as rt','rt.id','=','br.room_type_id')
            ->where(fn($q)=>$q->whereNull('rt.hotel_id')->orWhereColumn('rt.hotel_id','!=','b.hotel_id'))->exists();
        if ($badRooms || $badHistory) throw new \RuntimeException('Complete integration requires verified Room and BookingRoom remap.');
        config(['roomtype-v2.integration_complete'=>true]);
        RoomTypeV2WriteFence::install($db);
        app()->bind(\App\Services\BookingService::class,\App\Services\PreparedV2\BookingBridge::class);
        app()->bind(\App\Services\RoomAvailabilityService::class,\App\Services\PreparedV2\AvailabilityBridge::class);
        app()->bind(\App\Console\Commands\CancelExpiredBookings::class,function () {
            $command=app(\App\Console\Commands\PreparedV2CancelExpiredBookings::class);
            $command->setName('bookings:cancel-expired');
            return $command;
        });
        app(\Illuminate\Contracts\Console\Kernel::class)->registerCommand(app(\App\Console\Commands\CancelExpiredBookings::class));
        $schedule=app(\Illuminate\Console\Scheduling\Schedule::class);
        $hasDelivery=false;
        foreach ($schedule->events() as $event) if (str_contains($event->command??'','bookings:v2-deliver-prepared')) $hasDelivery=true;
        if (!$hasDelivery) $schedule->command('bookings:v2-deliver-prepared')->everyMinute();
        Route::middleware('web')->group(function () {
            Route::get('/hotels/{id}',[\App\Http\Controllers\PreparedV2\HotelDetailController::class,'show'])->name('hotel.detail');
            Route::get('/payment/result',[PaymentController::class,'htmlResult'])->name('payment.result');
            Route::get('/admin/hotels',[\App\Http\Controllers\PreparedV2\AdminHotelController::class,'page'])->name('admin.hotels');
            Route::get('/booking/{id}',[\App\Http\Controllers\PreparedV2\LegacyContractController::class,'unavailable'])->name('booking');
        });
        Route::middleware('api')->get('/api/hotels/{id}/rooms',[\App\Http\Controllers\PreparedV2\LegacyContractController::class,'unavailable']);
        Route::middleware(['api','auth:admin',\App\Http\Middleware\AdminMiddleware::class])->get('/api/admin/hotels',[\App\Http\Controllers\PreparedV2\AdminHotelController::class,'hotels']);
        Route::middleware(['api','auth:sanctum'])->get('/api/invoices/{booking_id}/pdf',[\App\Http\Controllers\PreparedV2\InvoiceController::class,'exportPdf'])->whereNumber('booking_id');
        self::prioritize();
    }

    public static function finalize(): void
    {
        $db=RoomTypeV2Database::connection('mysql');
        foreach (['manual_payment_verification','booking_state_audit','booking_effect_notification','payment_gateway_unmatched_event'] as $table) {
            if (!$db->getSchemaBuilder()->hasTable($table)) throw new \RuntimeException('Final integration requires payment policy audit schema.');
        }
        self::backend(); self::complete(); config(['roomtype-v2.final_integration'=>true]);
        require_once app_path('Services/HotelService.php');
        app()->bind(\App\Services\Hotel\HotelService::class,\App\Services\PreparedV2\HotelServiceBridge::class);
        app()->bind(\App\Services\Admin\HotelService::class,\App\Services\PreparedV2\AdminHotelServiceBridge::class);
        app()->bind(\App\Services\Admin\BookingService::class,\App\Services\PreparedV2\AdminBookingServiceBridge::class);
        $c=\App\Http\Controllers\PreparedV2\CatalogController::class;
        Route::middleware('web')->get('/',[$c,'home'])->name('home');
        Route::middleware('web')->get('/deals',[\App\Http\Controllers\PreparedV2\DealsController::class,'index'])->name('deals');
        Route::middleware('api')->group(function () use ($c) {
            Route::get('/api/hotels',[$c,'index']); Route::get('/api/hotels/{id}',[$c,'show'])->whereNumber('id');
            Route::get('/api/hotels/map',[$c,'map']); Route::get('/api/locations',[$c,'locations']);
        });
        $bank=\App\Http\Controllers\PreparedV2\BankingController::class;
        Route::middleware(['api','auth:sanctum'])->get('/api/payments/{booking}',[$bank,'detail'])->whereNumber('booking');
        Route::middleware(['api','auth:admin',\App\Http\Middleware\AdminMiddleware::class])->group(function () use ($bank) {
            Route::get('/api/admin/bookings',[$bank,'bookings']); Route::get('/api/admin/bookings/{booking}/payment-audit',[$bank,'audit'])->whereNumber('booking');
            Route::post('/api/admin/bookings/{booking}/payments/{payment}/verify-banking',[$bank,'verify'])->whereNumber(['booking','payment']);
            Route::get('/api/admin/customers/{id}',[$bank,'customerDetail'])->whereNumber('id');
        });
        Route::middleware('web')->get('/admin/bookings',[$bank,'adminPage'])->name('admin.bookings');
        Route::middleware('web')->get('/payments/bookings/{booking}',[$bank,'customerPage'])->whereNumber('booking');
        self::prioritize();
    }

    public static function acceptance(): void
    {
        $db=RoomTypeV2Database::connection('mysql');
        if (!$db->getSchemaBuilder()->hasTable('booking_delivery_audit') || !$db->getSchemaBuilder()->hasColumn('booking_effect','next_attempt_at')) throw new \RuntimeException('Delivery safety schema required.');
        self::finalize(); config(['roomtype-v2.delivery_safety'=>true]);
        $c=\App\Http\Controllers\PreparedV2\ReconciliationController::class;
        Route::middleware(['api','auth:admin',\App\Http\Middleware\AdminMiddleware::class])->group(function () use ($c) {
            Route::get('/api/admin/reconciliation',[$c,'index']);
            Route::post('/api/admin/delivery/{effect}/review',[$c,'review'])->whereNumber('effect');
            Route::get('/api/admin/delivery/{effect}/history',[$c,'history'])->whereNumber('effect');
        });
        Route::middleware('web')->get('/admin/reconciliation',[$c,'page']);
        Route::middleware('web')->get('/admin/hotels/{hotel}/room-types',[\App\Http\Controllers\PreparedV2\AdminRoomTypeController::class,'page'])->whereNumber('hotel');
        // Wrap authentication and exception responses too; install only for this guarded overlay.
        app(\Illuminate\Contracts\Http\Kernel::class)->prependMiddleware(\App\Http\Middleware\RoomTypeV2ResponsePolicy::class);
        self::prioritize();
    }
}
