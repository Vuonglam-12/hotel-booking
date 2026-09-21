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
    Route::get('/map',          [HotelController::class, 'mapData']);
    Route::get('/{id}',         [HotelController::class, 'show']);
    Route::get('/{id}/rooms',   [HotelController::class, 'rooms']);
    Route::get('/{id}/reviews', [ReviewController::class, 'hotelReviews']);
});

Route::get('/locations', [HotelController::class, 'locations']);

// VNPay callback — public vì VNPay tự gọi
Route::get('/payments/vnpay-return', [PaymentController::class, 'vnpayReturn']);

// ==========================================
// ADMIN đăng nhập/đăng xuất
// ==========================================
Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthAdminController::class, 'login']);

    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AuthAdminController::class, 'logout']);
        Route::get('/me',      [AuthAdminController::class, 'me']);

        // Rooms
        Route::get('/rooms/availability', [App\Http\Controllers\RoomController::class, 'checkAvailability']);

        // Dashboard
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // Quản lý khách sạn
        Route::get('/hotels',             [AdminController::class, 'hotels']);
        Route::post('/hotels',            [AdminController::class, 'createHotel']);
        Route::put('/hotels/{id}',        [AdminController::class, 'updateHotel']);
        Route::put('/hotels/{id}/status', [AdminController::class, 'updateHotelStatus']);
        
        // Quản lý phòng của khách sạn
        Route::get('/hotels/{hotelId}/room-types',  [AdminController::class, 'getRoomTypes']);
        Route::post('/hotels/{hotelId}/room-types', [AdminController::class, 'createRoomType']);

        // Quản lý khách hàng
        Route::get('/customers', [AdminController::class, 'customers']);
        Route::get('/customers/{id}',   [AdminController::class, 'customerDetail']);

        // Quản lý review
        Route::get('/reviews',          [AdminController::class, 'reviews']);
        Route::delete('/reviews/{id}',  [AdminController::class, 'deleteReview']);

        // Quản lý locations (điểm đến)
        Route::get('/locations',                     [AdminController::class, 'destinations']);
        Route::post('/locations',                    [AdminController::class, 'createDestination']);
        Route::put('/locations/{id}',                [AdminController::class, 'updateDestination']);
        Route::put('/locations/{id}/toggle',         [AdminController::class, 'toggleDestination']);

        // Quản lý attractions
        Route::get('/locations/{locId}/attractions',    [AdminController::class, 'attractions']);
        Route::post('/locations/{locId}/attractions',   [AdminController::class, 'createAttraction']);
        Route::put('/attractions/{id}',                 [AdminController::class, 'updateAttraction']);
        Route::delete('/attractions/{id}',              [AdminController::class, 'deleteAttraction']);
        Route::put('/attractions/{id}/toggle',          [AdminController::class, 'toggleAttraction']);

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
    Route::get('/bookings/my',            [BookingController::class, 'myBookings']);
    Route::get('/bookings/{id}',          [BookingController::class, 'show']);
    Route::post('/bookings/{id}/cancel',  [BookingController::class, 'cancel']);

    // Wishlist
    Route::get('/wishlist',                    [WishlistController::class, 'index']);
    Route::post('/wishlist/{hotel_id}',        [WishlistController::class, 'add']);
    Route::delete('/wishlist/{hotel_id}',      [WishlistController::class, 'remove']);
    Route::post('/wishlist/{hotel_id}/toggle', [WishlistController::class, 'toggle']);

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
    Route::post('/chat/{session_id}/message', [ChatController::class, 'sendMessage']);
    Route::put('/chat/{session_id}/close',    [ChatController::class, 'closeSession']);

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