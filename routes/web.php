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
Route::get('/deals', [DealsController::class, 'index'])->name('deals');
Route::get('/blog', [BlogController::class, 'index'])->name('blog');
Route::get('/blog/{id}', [BlogController::class, 'detail'])->name('blog.detail');


// Dashboard — cần đăng nhập (check bằng JS)
Route::get('/dashboard',    [DashboardController::class, 'index'])->name('dashboard');

Route::get('/payment/result', [WebController::class, 'paymentResult'])->name('payment.result');

Route::get('/reset-password/{token}', function ($token) {
    return view('auth.reset_password', [
        'token' => $token,
        'email' => request('email'),
    ]);
});