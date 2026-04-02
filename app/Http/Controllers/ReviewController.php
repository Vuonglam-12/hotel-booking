<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Booking;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    // ==========================================
    // Xem tất cả review của 1 KS
    // GET /api/hotels/{hotel_id}/reviews
    // ==========================================
    public function hotelReviews(Request $request, $hotel_id)
    {
        $reviews = Review::with([
            'customer:id,name,avatar_url', // Chỉ lấy 3 field của customer, không lấy password
            'images',                       // Kèm ảnh review
        ])
        ->where('hotel_id', $hotel_id)
        ->orderBy('created_at', 'desc')
        ->paginate($request->per_page ?? 10);

        // Tính điểm trung bình từng hạng mục
        $stats = DB::table('review')
            ->where('hotel_id', $hotel_id)
            ->selectRaw('
                COUNT(*) as total,
                AVG(rating) as avg_overall,
                AVG(rating_cleanliness) as avg_cleanliness,
                AVG(rating_service) as avg_service,
                AVG(rating_location) as avg_location
            ')
            ->first();

        return response()->json([
            'stats'   => $stats,   // Thống kê tổng hợp
            'reviews' => $reviews, // Danh sách review có phân trang
        ]);
    }

    // ==========================================
    // Viết review cho KS
    // POST /api/reviews
    // ==========================================
    public function store(Request $request)
    {
        // Validate dữ liệu đầu vào
        $request->validate([
            'hotel_id'           => 'required|integer|exists:hotel,id',
            'booking_id'         => 'nullable|integer|exists:booking,id',
            'rating'             => 'required|integer|between:1,5',
            'rating_cleanliness' => 'nullable|integer|between:1,5',
            'rating_service'     => 'nullable|integer|between:1,5',
            'rating_location'    => 'nullable|integer|between:1,5',
            'comment'            => 'nullable|string|max:2000',
        ]);

        $customerId = auth('sanctum')->id();

        // Nếu có booking_id → verify booking đó có phải của user này không
        // và booking phải ở KS mà họ muốn review
        if ($request->booking_id) {
            $booking = Booking::where('id', $request->booking_id)
                ->where('customer_id', $customerId)
                ->where('hotel_id', $request->hotel_id)
                ->whereIn('status', ['confirmed', 'completed'])
                ->first();

            if (!$booking) {
                return response()->json([
                    'message' => 'Booking không hợp lệ hoặc không thuộc về bạn',
                ], 422);
            }
        }

        // Kiểm tra đã review KS này chưa (1 user chỉ review 1 lần / 1 KS)
        $alreadyReviewed = Review::where('customer_id', $customerId)
            ->where('hotel_id', $request->hotel_id)
            ->exists();

        if ($alreadyReviewed) {
            return response()->json([
                'message' => 'Bạn đã review KS này rồi',
            ], 422);
        }

        // Tạo review
        $review = Review::create([
            'customer_id'        => $customerId,
            'booking_id'         => $request->booking_id,
            'hotel_id'           => $request->hotel_id,
            'rating'             => $request->rating,
            'rating_cleanliness' => $request->rating_cleanliness,
            'rating_service'     => $request->rating_service,
            'rating_location'    => $request->rating_location,
            'comment'            => $request->comment,
            'helpful_count'      => 0,
            'created_at'         => now(),
        ]);

        // Cập nhật avg_rating trong bảng hotel (denormalized để query nhanh)
        $avgRating = Review::where('hotel_id', $request->hotel_id)->avg('rating');
        Hotel::where('id', $request->hotel_id)->update(['avg_rating' => round($avgRating, 1)]);

        return response()->json([
            'message' => 'Cảm ơn bạn đã review!',
            'review'  => $review->load('customer:id,name,avatar_url'),
        ], 201);
    }

    // ==========================================
    // Xóa review (chỉ xóa được review của mình)
    // DELETE /api/reviews/{id}
    // ==========================================
    public function destroy($id)
    {
        $review = Review::where('id', $id)
            ->where('customer_id', auth('sanctum')->id())
            ->firstOrFail();

        $hotelId = $review->hotel_id;
        $review->delete();

        // Cập nhật lại avg_rating sau khi xóa
        $avgRating = Review::where('hotel_id', $hotelId)->avg('rating') ?? 0;
        Hotel::where('id', $hotelId)->update(['avg_rating' => round($avgRating, 1)]);

        return response()->json([
            'message' => 'Đã xóa review',
        ]);
    }

    // ==========================================
    // Đánh dấu review hữu ích
    // POST /api/reviews/{id}/helpful
    // ==========================================
    public function helpful($id)
    {
        // Tăng helpful_count lên 1
        Review::where('id', $id)->increment('helpful_count');

        return response()->json([
            'message' => 'Cảm ơn phản hồi của bạn!',
        ]);
    }

    // ==========================================
    // Xem review của user đang đăng nhập
    // GET /api/reviews/my
    // ==========================================
    public function myReviews()
    {
        $reviews = Review::with(['hotel:id,name,star_rating', 'images'])
            ->where('customer_id', auth('sanctum')->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reviews);
    }
}