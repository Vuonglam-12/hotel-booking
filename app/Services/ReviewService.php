<?php

namespace App\Services\Review;

use App\Models\Booking;
use App\Models\CustomerNotification as Notification;
use App\Models\Hotel;
use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function getHotelReviews(int $hotelId, array $filters): array
    {
        $reviews = Review::with(['customer:id,name,avatar_url', 'images'])
            ->where('hotel_id', $hotelId)
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 10);

        $stats = DB::table('review')
            ->where('hotel_id', $hotelId)
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg_overall, AVG(rating_cleanliness) as avg_cleanliness, AVG(rating_service) as avg_service, AVG(rating_location) as avg_location')
            ->first();

        return ['stats' => $stats, 'reviews' => $reviews];
    }

    public function create(int $customerId, array $data): Review
    {
        if (!empty($data['booking_id'])) {
            $valid = Booking::where('id', $data['booking_id'])
                ->where('customer_id', $customerId)
                ->where('hotel_id', $data['hotel_id'])
                ->whereIn('status', ['confirmed', 'completed'])
                ->exists();

            if (!$valid) {
                throw new \Exception('Booking không hợp lệ hoặc không thuộc về bạn');
            }
        }

        if (Review::where('customer_id', $customerId)->where('hotel_id', $data['hotel_id'])->exists()) {
            throw new \Exception('Bạn đã review KS này rồi');
        }

        $review = Review::create([
            'customer_id'        => $customerId,
            'booking_id'         => $data['booking_id']         ?? null,
            'hotel_id'           => $data['hotel_id'],
            'rating'             => $data['rating'],
            'rating_cleanliness' => $data['rating_cleanliness'] ?? null,
            'rating_service'     => $data['rating_service']     ?? null,
            'rating_location'    => $data['rating_location']    ?? null,
            'comment'            => $data['comment']            ?? null,
            'helpful_count'      => 0,
            'created_at'         => now(),
        ]);

        $this->recalcAvgRating($data['hotel_id']);

        $hotelName = Hotel::where('id', $data['hotel_id'])->value('name');
        Notification::reviewSubmitted($customerId, $review->id, $hotelName, $data['rating']);

        return $review->load('customer:id,name,avatar_url');
    }

    public function delete(int $id, int $customerId): void
    {
        $review = Review::where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $hotelId = $review->hotel_id;
        $review->delete();

        $this->recalcAvgRating($hotelId);
    }

    public function incrementHelpful(int $id): void
    {
        Review::where('id', $id)->increment('helpful_count');
    }

    public function myReviews(int $customerId): \Illuminate\Database\Eloquent\Collection
    {
        return Review::with(['hotel:id,name,star_rating', 'images'])
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function recalcAvgRating(int $hotelId): void
    {
        $avg = Review::where('hotel_id', $hotelId)->avg('rating') ?? 0;
        Hotel::where('id', $hotelId)->update(['avg_rating' => round($avg, 1)]);
    }
}