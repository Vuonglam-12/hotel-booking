<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealsController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ExperienceController;

/*
|--------------------------------------------------------------------------
| Web Routes — Blade views
|--------------------------------------------------------------------------
*/

Route::get('/',             [WebController::class, 'home'])->name('home');
Route::get('/hotels/{id}',  [WebController::class, 'hotelDetail'])->name('hotel.detail');
Route::get('/booking/{id}', [WebController::class, 'booking'])->name('booking');
Route::get('/login',        [WebController::class, 'login'])->name('login');
Route::get('/register',     [WebController::class, 'register'])->name('register');
Route::get('/deals',        [DealsController::class, 'index'])->name('deals');
Route::get('/blog',         [BlogController::class, 'index'])->name('blog');
Route::get('/blog/{id}',    [BlogController::class, 'detail'])->name('blog.detail');
Route::get('/cam-nang', [\App\Http\Controllers\GuideController::class, 'index'])->name('guides.index');
Route::get('/cam-nang/{slug}', [\App\Http\Controllers\GuideController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('guides.show');
Route::get('/trai-nghiem', [ExperienceController::class, 'index'])->name('experiences.index');
Route::get('/trai-nghiem/{slug}', [ExperienceController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('experiences.show');


// Dashboard — cần đăng nhập (check bằng JS)
Route::get('/dashboard',    [DashboardController::class, 'index'])->name('dashboard');

Route::get('/payment/result', [WebController::class, 'paymentResult'])->name('payment.result');
Route::middleware(\App\Http\Middleware\LocalBookingDemoOnly::class)->group(function () {
    Route::get('/admin/payment-reconciliation', fn()=>view('admin.payment-reconciliation'));
    Route::get('/payments/bookings/{booking}', [\App\Http\Controllers\LocalBookingDemoController::class,'page'])->whereNumber('booking');
    Route::get('/demo/admin/payments/{id}', [\App\Http\Controllers\LocalBookingDemoController::class,'adminPage'])->whereNumber('id');
});

Route::get('/profile', fn() => view('user.profile'));
Route::get('/password', fn() => view('user.password'));
Route::get('/bookings', fn() => view('user.booking'));
Route::get('/itineraries', fn() => view('user.itineraries'));
Route::get('/settings', fn() => view('user.settings'));

Route::get('/reset-password/{token}', function ($token) {
    return view('auth.reset_password', [
        'token' => $token,
        'email' => request('email'),
    ]);
});


// ==========================================
// ADMIN ROUTES - Blade views
// ==========================================
Route::prefix('admin')->name('admin.')->group(function () {
    
    // Login page (không cần middleware)
    Route::get('/login', function () {
        return view('admin.login');
    })->name('login');
    
    // Các trang cần đăng nhập (middleware sẽ check token ở frontend JS)
    Route::middleware(['web'])->group(function () {
        Route::get('/experiences', [\App\Http\Controllers\Admin\AttractionController::class, 'page'])->name('experiences');
        Route::get('/guides', [\App\Http\Controllers\Admin\GuideController::class, 'page'])->name('guides');
        Route::get('/', function () { 
            return view('admin.dashboard');
        })->name('dashboard');
        
        Route::get('/bookings', function () { // Đây là trang quản lý booking của admin
            return view('admin.bookings');
        })->name('bookings');
        
        Route::get('/hotels', function () { // Đay là trang quản lý hotel của admin
            return view('admin.hotels');
        })->name('hotels');

        Route::get('/hotels/{id}', function (int $id) {
            return view('admin.hotel-detail', ['hotelId' => $id]);
        })->whereNumber('id')->name('hotels.show');
        
        Route::get('/customers', function () { // Đây là trang quản lý khách hàng của admin
            return view('admin.customers');
        })->name('customers');
        
        Route::get('/reviews', function () { // Đây là trang quản lý đánh giá của admin 
            return view('admin.reviews');
        })->name('reviews');
        
        Route::get('/revenue', function () { // Đây là trang quản lý doanh thu của admin
            return view('admin.revenue');
        })->name('revenue');
    });
});
