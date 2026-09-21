<?php

namespace App\Services\Admin;

use App\Models\Hotel;
use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReviewService
{
    public function getList(array $filters): LengthAwarePaginator
    {
        $query = Review::with(['customer:id,name', 'hotel:id,name'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['max_rating'])) {
            $query->where('rating', '<=', $filters['max_rating']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function delete(int $id): void
    {
        $review  = Review::findOrFail($id);
        $hotelId = $review->hotel_id;
        $review->delete();

        $avg = Review::where('hotel_id', $hotelId)->avg('rating') ?? 0;
        Hotel::where('id', $hotelId)->update(['avg_rating' => round($avg, 1)]);
    }
}