<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // Trang dashboard — load data bằng JS gọi API
    // Không cần truyền data từ PHP vì frontend tự gọi API
    public function index()
    {
        return view('dashboard');
    }
}