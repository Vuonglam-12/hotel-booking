<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ItineraryController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthAdminController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;

// ==========================================
// PUBLIC — không cần đăng nhập
// ==========================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/auth/login-phone',            [AuthController::class, 'loginByPhone']);
Route::post('/forgot-password',             [AuthController::class, 'forgotPassword']);
Route::post('/forgot-password-otp',         [AuthController::class, 'forgotPasswordOtp']);
Route::post('/forgot-password-verify-otp',  [AuthController::class, 'forgotPasswordVerifyOtp']);
Route::post('/reset-password',              [AuthController::class, 'resetPassword']);
Route::post('/auth/google',                 [AuthController::class, 'loginGoogle']);

Route::prefix('hotels')->group(function () {
    Route::get('/',             [HotelController::class, 'index']);
    Route::get('/suggestions',  [HotelController::class, 'suggestions']);
    Route::get('/map',          [HotelController::class, 'mapData']);
    Route::get('/{id}',         [HotelController::class, 'show']);
    Route::get('/{id}/rooms',   [HotelController::class, 'rooms']);
    Route::get('/{id}/reviews', [ReviewController::class, 'hotelReviews']);
    Route::get('/{hotel}/room-types/{type}/quote', [\App\Http\Controllers\RoomPricingController::class, 'quote'])->whereNumber(['hotel', 'type']);
});

Route::get('/locations', [HotelController::class, 'locations']);
Route::prefix('guides')->controller(\App\Http\Controllers\GuideController::class)->group(function () {
    Route::get('/', 'listing');
    Route::get('/{slug}', 'detail')->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
});

Route::prefix('experiences')->controller(\App\Http\Controllers\ExperienceController::class)->group(function () {
    Route::get('/', 'apiIndex');
    Route::get('/filters', 'apiFilters');
    Route::get('/{slug}', 'apiShow')->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
});

// SePay callbacks are independently disabled until test-mode credentials and V2 guards are approved.
Route::post('/webhooks/sepay', \App\Http\Controllers\SePayWebhookController::class);

// VNPay callback — public vì VNPay tự gọi
Route::get('/payments/vnpay-return', [PaymentController::class, 'vnpayReturn']);

// ==========================================
// ADMIN đăng nhập/đăng xuất
// ==========================================
Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthAdminController::class, 'login']);

    Route::middleware(['auth:admin', \App\Http\Middleware\AdminMiddleware::class])->group(function () {
        Route::middleware(\App\Http\Middleware\LocalBookingDemoOnly::class)->group(function () {
            $c = \App\Http\Controllers\LocalBookingDemoController::class;
            Route::get('/demo/bookings', [$c,'listing']);
            Route::get('/demo/bookings/{id}', [$c,'adminDetail'])->whereNumber('id');
            Route::post('/demo/bookings/{id}/simulate', [$c,'adminSimulate'])->whereNumber('id');
            Route::post('/demo/bookings/{id}/retry', [$c,'adminRetry'])->whereNumber('id');
            Route::get('/demo/reconciliation', [\App\Http\Controllers\LocalBookingReconciliationController::class, 'index']);
            Route::post('/demo/reconciliation/{id}/review', [\App\Http\Controllers\LocalBookingReconciliationController::class, 'review'])->whereNumber('id');
        });
        Route::prefix('guides')->controller(\App\Http\Controllers\Admin\GuideController::class)->group(function () {
            Route::get('/meta', 'meta');
            Route::post('/preview', 'preview');
            Route::post('/categories', 'storeCategory');
            Route::put('/categories/{id}', 'updateCategory')->whereNumber('id');
            Route::delete('/categories/{id}', 'destroyCategory')->whereNumber('id');
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show')->whereNumber('id');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
            Route::post('/{id}/restore', 'restore')->whereNumber('id');
        });
        Route::post('/logout', [AuthAdminController::class, 'logout']);
        Route::get('/me',      [AuthAdminController::class, 'me']);

        // Rooms
        Route::get('/rooms/availability', [App\Http\Controllers\RoomController::class, 'checkAvailability']);

        // Dashboard
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // Quản lý khách sạn
        Route::get('/amenities',         [AdminController::class, 'hotelAmenities']);
        Route::post('/amenities',        [AdminController::class, 'createHotelAmenity']);
        Route::get('/hotels',             [AdminController::class, 'hotels']);
        Route::get('/hotels/{id}',        [AdminController::class, 'hotelDetail'])->whereNumber('id');
        Route::post('/hotels',            [AdminController::class, 'createHotel']);
        Route::put('/hotels/{id}',        [AdminController::class, 'updateHotel']);
        Route::get('/hotels/{id}/images', [AdminController::class, 'hotelImages'])->whereNumber('id');
        Route::put('/hotels/{id}/status', [AdminController::class, 'updateHotelStatus']);
        
        // Quản lý phòng của khách sạn
        Route::get('/hotels/{hotelId}/room-types',  [AdminController::class, 'getRoomTypes']);
        Route::post('/hotels/{hotelId}/room-types', [AdminController::class, 'createRoomType']);
        Route::get('/hotels/{hotel}/room-types/{type}', [AdminController::class, 'roomTypeDetail'])->whereNumber(['hotel', 'type']);
        Route::put('/hotels/{hotel}/room-types/{type}', [AdminController::class, 'updateRoomType'])->whereNumber(['hotel', 'type']);
        Route::delete('/hotels/{hotel}/room-types/{type}', [AdminController::class, 'deleteRoomType'])->whereNumber(['hotel', 'type']);
        Route::get('/hotels/{hotel}/room-types/{type}/images', [AdminController::class, 'roomTypeImages'])->whereNumber(['hotel', 'type']);
        Route::get('/hotels/{hotel}/room-types/{type}/rates', [\App\Http\Controllers\RoomPricingController::class, 'index'])->whereNumber(['hotel', 'type']);
        Route::post('/hotels/{hotel}/room-types/{type}/rates', [\App\Http\Controllers\RoomPricingController::class, 'store'])->whereNumber(['hotel', 'type']);
        Route::put('/hotels/{hotel}/room-types/{type}/rates/{rate}', [\App\Http\Controllers\RoomPricingController::class, 'update'])->whereNumber(['hotel', 'type', 'rate']);
        Route::delete('/hotels/{hotel}/room-types/{type}/rates/{rate}', [\App\Http\Controllers\RoomPricingController::class, 'destroy'])->whereNumber(['hotel', 'type', 'rate']);

        // Quản lý khách hàng
        Route::get('/customers', [AdminController::class, 'customers']);
        Route::get('/customers/{id}',   [AdminController::class, 'customerDetail']);

        // Quản lý review
        Route::get('/reviews',          [AdminController::class, 'reviews']);
        Route::patch('/reviews/{id}/status', [AdminController::class, 'updateReviewStatus'])->whereNumber('id');
        Route::delete('/reviews/{id}',  [AdminController::class, 'deleteReview']);

        // Quản lý locations (điểm đến)
        Route::get('/locations',                     [AdminController::class, 'destinations']);
        Route::post('/locations',                    [AdminController::class, 'createDestination']);
        Route::put('/locations/{id}',                [AdminController::class, 'updateDestination']);
        Route::put('/locations/{id}/toggle',         [AdminController::class, 'toggleDestination']);

        // Quản lý attractions
        Route::get('/experiences/meta', [\App\Http\Controllers\Admin\AttractionController::class, 'meta']);
        Route::get('/attractions', [\App\Http\Controllers\Admin\AttractionController::class, 'listing']);
        Route::get('/locations/{locId}/attractions', [\App\Http\Controllers\Admin\AttractionController::class, 'index'])->whereNumber('locId');
        Route::post('/locations/{locId}/attractions', [\App\Http\Controllers\Admin\AttractionController::class, 'store'])->whereNumber('locId');
        Route::get('/attractions/{id}', [\App\Http\Controllers\Admin\AttractionController::class, 'show'])->whereNumber('id');
        Route::put('/attractions/{id}', [\App\Http\Controllers\Admin\AttractionController::class, 'update'])->whereNumber('id');
        Route::delete('/attractions/{id}', [\App\Http\Controllers\Admin\AttractionController::class, 'destroy'])->whereNumber('id');
        Route::put('/attractions/{id}/toggle', [\App\Http\Controllers\Admin\AttractionController::class, 'toggle'])->whereNumber('id');
        Route::post('/attractions/{id}/restore', [\App\Http\Controllers\Admin\AttractionController::class, 'restore'])->whereNumber('id');

        // Báo cáo doanh thu
        Route::get('/revenue', [AdminController::class, 'revenue']);

        // Quản lý đặt phòng
        Route::get('/bookings', [AdminController::class, 'bookings']);
        Route::put('/bookings/{id}/status', [AdminController::class, 'updateBookingStatus']);

        // Gửi email cho khách hàng
        Route::post('/customers/{id}/send-email', [AdminController::class, 'sendEmailToCustomer']);
           
    });
});

// ==========================================
// PROTECTED — Customer cần đăng nhập
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
    Route::post('/auth/send-phone-otp',   [AuthController::class, 'sendPhoneOtp']);
    Route::post('/auth/verify-phone-otp', [AuthController::class, 'verifyPhoneOtp']);
    Route::put('/me',           [AuthController::class, 'updateProfile']);
    Route::post('/me/password', [AuthController::class, 'updatePassword']);

    // Booking
    Route::post('/bookings',              [BookingController::class, 'store']);
    Route::middleware(\App\Http\Middleware\LocalBookingDemoOnly::class)->group(function () {
        $c = \App\Http\Controllers\LocalBookingDemoController::class;
        Route::get('/demo/bookings/{id}', [$c,'detail'])->whereNumber('id');
        Route::post('/demo/bookings/{id}/simulate', [$c,'simulate'])->whereNumber('id');
        Route::post('/demo/bookings/{id}/retry', [$c,'retry'])->whereNumber('id');
    });
    Route::get('/bookings/my',            [BookingController::class, 'myBookings']);
    Route::get('/bookings/{id}',          [BookingController::class, 'show']);
    Route::post('/bookings/{id}/cancel',  [BookingController::class, 'cancel']);

    // Wishlist
    Route::get('/wishlist',                    [WishlistController::class, 'index']);
    Route::post('/wishlist/{hotel_id}',        [WishlistController::class, 'add'])->whereNumber('hotel_id');
    Route::delete('/wishlist/{hotel_id}',      [WishlistController::class, 'remove'])->whereNumber('hotel_id');
    Route::post('/wishlist/{hotel_id}/toggle', [WishlistController::class, 'toggle'])->whereNumber('hotel_id');

    // Review
    Route::get('/reviews/my',            [ReviewController::class, 'myReviews']);
    Route::post('/reviews',              [ReviewController::class, 'store']);
    Route::delete('/reviews/{id}',       [ReviewController::class, 'destroy']);
    Route::post('/reviews/{id}/helpful', [ReviewController::class, 'helpful']);

    // Payment
    Route::post('/payments/create',         [PaymentController::class, 'createPayment']);
    Route::post('/payments/manual',         [PaymentController::class, 'createManual']);
    Route::post('/payments/retry',          [PaymentController::class, 'retry']);
    Route::get('/payments/{booking_id}',    [PaymentController::class, 'show']);

    // Invoice
    Route::get('/invoices',                  [InvoiceController::class, 'index']);
    Route::get('/invoices/{booking_id}',     [InvoiceController::class, 'show']);
    Route::get('/invoices/{booking_id}/pdf', [InvoiceController::class, 'exportPdf']);

    // Chatbot
    Route::post('/chat/start',                [ChatController::class, 'startSession']);
    Route::get('/chat',                       [ChatController::class, 'mySessions']);
    Route::get('/chat/{session_id}',          [ChatController::class, 'getSession']);
    Route::post('/chat/{session_id}/message', [ChatController::class, 'sendMessage'])->middleware('throttle:12,1');
    Route::get('/chat/{session_id}/messages/{message_id}/route/{day}', [ChatController::class, 'routeDay'])
        ->whereNumber(['session_id','message_id','day'])->middleware('throttle:20,1');
    Route::put('/chat/{session_id}/close',    [ChatController::class, 'closeSession']);
    Route::delete('/chat/{session_id}', [ChatController::class, 'destroySession'])->whereNumber('session_id')->middleware('throttle:20,1');

    // Trip management v2. Existing pages continue using the legacy read contract.
    Route::prefix('v2/itineraries')->middleware(\App\Http\Middleware\ItineraryV2Access::class)
        ->controller(\App\Http\Controllers\Api\V2\ItineraryController::class)->group(function () {
            Route::get('/destinations', 'destinations');
            Route::post('/generate', 'generate')->middleware('throttle:6,1');
            Route::post('/planner/generate', [\App\Http\Controllers\Api\V2\ItineraryPlannerController::class, 'generate'])->middleware('throttle:6,1');
            Route::post('/planner/check-hours', [\App\Http\Controllers\Api\V2\ItineraryPlannerController::class, 'checkHours']);
            Route::post('/planner/place-photo', [\App\Http\Controllers\Api\V2\ItineraryPlannerController::class, 'placePhoto'])->middleware('throttle:120,1');
            Route::get('/places/{id}/hours', [\App\Http\Controllers\Api\V2\ItineraryPlannerController::class, 'placeHours'])->whereNumber('id');
            Route::post('/planner', [\App\Http\Controllers\Api\V2\ItineraryPlannerController::class, 'write']);
            Route::put('/{id}/planner', [\App\Http\Controllers\Api\V2\ItineraryPlannerController::class, 'write'])->whereNumber('id');
            Route::get('/{id}/pdf', [\App\Http\Controllers\Api\V2\ItineraryPlannerController::class, 'pdf'])->whereNumber('id');
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show')->whereNumber('id');
            Route::put('/{id}', 'update')->whereNumber('id');
            Route::patch('/{id}', 'status')->whereNumber('id');
            Route::delete('/{id}', 'destroy')->whereNumber('id');
        });

    // Itineraries
    Route::prefix('itineraries')->group(function () {
        Route::get('/',              [ItineraryController::class, 'index']);
        Route::post('/generate',     [ItineraryController::class, 'generate']);
        Route::get('/{id}',          [ItineraryController::class, 'show']);
        Route::put('/{id}',          [ItineraryController::class, 'update']);
        Route::delete('/{id}',       [ItineraryController::class, 'destroy']);
        Route::post('/{id}/chat',    [ItineraryController::class, 'chat']);
        Route::put('/{id}/items/{itemId}',    [ItineraryController::class, 'updateItem']);
        Route::delete('/{id}/items/{itemId}', [ItineraryController::class, 'deleteItem']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/',              [NotificationController::class, 'index']);
        Route::get('/unread-count',  [NotificationController::class, 'unreadCount']);
        Route::post('/read-all',     [NotificationController::class, 'markAllRead']);
        Route::post('/{id}/read',    [NotificationController::class, 'markRead']);
        Route::delete('/delete-all', [NotificationController::class, 'deleteAll']);
        Route::delete('/{id}',       [NotificationController::class, 'destroy']);
    });

    // Cập nhật avatar
    Route::post('/me/avatar', [AuthController::class, 'updateAvatar']);
});
