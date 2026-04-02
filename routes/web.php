<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
use App\Http\Controllers\DashboardController;

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

// Dashboard — cần đăng nhập (check bằng JS)
Route::get('/dashboard',    [DashboardController::class, 'index'])->name('dashboard');

Route::get('/payment/result', [WebController::class, 'paymentResult'])->name('payment.result');

Route::get('/reset-password/{token}', function ($token) {
    return view('reset_password', [
        'token' => $token,
        'email' => request('email'),
    ]);
});