<?php

namespace App\Services\Admin;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class RevenueService
{
    public function getSummary(): array
    {
        return [
            'monthly_revenue' => $this->monthlyRevenue(),
            'top_hotels'      => $this->topHotels(),
            'total_revenue'   => Payment::where('payment_status', 'success')->sum('amount'),
        ];
    }

    private function monthlyRevenue(): \Illuminate\Support\Collection
    {
        return Payment::where('payment_status', 'success')
            ->whereYear('paid_at', now()->year)
            ->selectRaw('MONTH(paid_at) as month, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    private function topHotels(): \Illuminate\Support\Collection
    {
        return DB::table('payment as p')
            ->join('booking as b', 'b.id', '=', 'p.booking_id')
            ->join('hotel as h', 'h.id', '=', 'b.hotel_id')
            ->where('p.payment_status', 'success')
            ->selectRaw('h.id, h.name, SUM(p.amount) as revenue, COUNT(p.id) as transactions')
            ->groupBy('h.id', 'h.name')
            ->orderByDesc('revenue')
            ->take(5)
            ->get();
    }
}