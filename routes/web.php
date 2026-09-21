<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealsController;
use App\Http\Controllers\BlogController;

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


// Dashboard — cần đăng nhập (check bằng JS)
Route::get('/dashboard',    [DashboardController::class, 'index'])->name('dashboard');

Route::get('/payment/result', [WebController::class, 'paymentResult'])->name('payment.result');

Route::get('/profile', fn() => view('user.profile'));
Route::get('/password', fn() => view('user.password'));
Route::get('/bookings', fn() => view('user.booking'));
Route::get('/itineraries', fn() => view('user.itineraries'));

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
        Route::get('/', function () { 
            return view('admin.dashboard');
        })->name('dashboard');
        
        Route::get('/bookings', function () { // Đây là trang quản lý booking của admin
            return view('admin.bookings');
        })->name('bookings');
        
        Route::get('/hotels', function () { // Đay là trang quản lý hotel của admin
            return view('admin.hotels');
        })->name('hotels');
        
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