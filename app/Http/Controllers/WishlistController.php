<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    // Xem danh sách KS yêu thích
    public function index()
    {
        $wishlist = Wishlist::with(['hotel' => function ($q) {
            $q->with(['images' => function ($q) {
                $q->where('is_primary', true);
            }, 'location', 'amenities']);
        }])
        ->where('customer_id', auth('sanctum')->id())
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json($wishlist);
    }

    // Thêm KS vào wishlist
    public function add($hotel_id)
    {
        $customerId = auth('sanctum')->id();

        // Check đã có chưa
        $exists = Wishlist::where('customer_id', $customerId)
            ->where('hotel_id', $hotel_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'KS đã có trong danh sách yêu thích',
            ], 422);
        }

        Wishlist::create([
            'customer_id' => $customerId,
            'hotel_id'    => $hotel_id,
            'created_at'  => now(),
        ]);

        return response()->json([
            'message' => 'Đã thêm vào danh sách yêu thích ❤️',
        ], 201);
    }

    // Xóa KS khỏi wishlist
    public function remove($hotel_id)
    {
        $deleted = Wishlist::where('customer_id', auth('sanctum')->id())
            ->where('hotel_id', $hotel_id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'KS không có trong danh sách yêu thích',
            ], 404);
        }

        return response()->json([
            'message' => 'Đã xóa khỏi danh sách yêu thích',
        ]);
    }

    // Toggle — bấm 1 lần thêm, bấm lại xóa
    public function toggle($hotel_id)
    {
        $customerId = auth('sanctum')->id();

        $wishlist = Wishlist::where('customer_id', $customerId)
            ->where('hotel_id', $hotel_id)
            ->first();

        if ($wishlist) {
            $wishlist->delete();
            return response()->json([
                'message' => 'Đã xóa khỏi danh sách yêu thích',
                'liked'   => false,
            ]);
        }

        Wishlist::create([
            'customer_id' => $customerId,
            'hotel_id'    => $hotel_id,
            'created_at'  => now(),
        ]);

        return response()->json([
            'message' => 'Đã thêm vào danh sách yêu thích ❤️',
            'liked'   => true,
        ], 201);
    }
}