<?php

namespace App\Services\Wishlist;

use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Collection;

class WishlistService
{
    public function getList(int $customerId): Collection
    {
        return Wishlist::with(['hotel' => fn($q) =>
            $q->with(['images' => fn($q) => $q->where('is_primary', true), 'location', 'amenities'])
        ])
        ->where('customer_id', $customerId)
        ->orderBy('created_at', 'desc')
        ->get();
    }

    public function add(int $customerId, int $hotelId): void
    {
        if (Wishlist::where('customer_id', $customerId)->where('hotel_id', $hotelId)->exists()) {
            throw new \Exception('KS đã có trong danh sách yêu thích');
        }

        Wishlist::create(['customer_id' => $customerId, 'hotel_id' => $hotelId, 'created_at' => now()]);
    }

    public function remove(int $customerId, int $hotelId): void
    {
        $deleted = Wishlist::where('customer_id', $customerId)->where('hotel_id', $hotelId)->delete();

        if (!$deleted) {
            throw new \Exception('KS không có trong danh sách yêu thích');
        }
    }

    public function toggle(int $customerId, int $hotelId): bool
    {
        $wishlist = Wishlist::where('customer_id', $customerId)->where('hotel_id', $hotelId)->first();

        if ($wishlist) {
            $wishlist->delete();
            return false;
        }

        Wishlist::create(['customer_id' => $customerId, 'hotel_id' => $hotelId, 'created_at' => now()]);
        return true;
    }
}