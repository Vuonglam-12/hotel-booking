<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerNotification;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function getHotelReviews(int $hotelId, array $filters): array
    {
        Hotel::where('status','active')->findOrFail($hotelId);
        $query=Review::published()->with($this->relations(['customer:id,name,avatar_url']))->where('hotel_id',$hotelId);
        if(!empty($filters['rating']))$query->where('rating',(int)$filters['rating']);
        if(($filters['sort']??'newest')==='highest')$query->orderByDesc('rating');
        elseif(($filters['sort']??'newest')==='lowest')$query->orderBy('rating');
        return [
            'stats'=>$this->summary($hotelId),
            'reviews'=>$query->orderByDesc('created_at')->orderByDesc('id')->paginate($filters['per_page']??10,['*'],'page',$filters['page']??1),
        ];
    }

    public function summary(int $hotelId): array
    {
        $row=Review::published()->where('hotel_id',$hotelId)->selectRaw('COUNT(*) total, AVG(rating) avg_overall,
            AVG(rating_cleanliness) avg_cleanliness, COUNT(rating_cleanliness) cleanliness_count,
            AVG(rating_service) avg_service, COUNT(rating_service) service_count,
            AVG(rating_location) avg_location, COUNT(rating_location) location_count,
            SUM(CASE WHEN rating=5 THEN 1 ELSE 0 END) stars_5, SUM(CASE WHEN rating=4 THEN 1 ELSE 0 END) stars_4,
            SUM(CASE WHEN rating=3 THEN 1 ELSE 0 END) stars_3, SUM(CASE WHEN rating=2 THEN 1 ELSE 0 END) stars_2,
            SUM(CASE WHEN rating=1 THEN 1 ELSE 0 END) stars_1')->first();
        $summary=['total'=>(int)$row->total,'rating_counts'=>[]];
        foreach(['overall','cleanliness','service','location'] as $key)$summary['avg_'.$key]=$row->{'avg_'.$key}===null?null:(float)$row->{'avg_'.$key};
        foreach(['cleanliness','service','location'] as $key)$summary[$key.'_count']=(int)$row->{$key.'_count'};
        foreach(range(5,1) as $score)$summary['rating_counts'][$score]=(int)$row->{'stars_'.$score};
        return $summary;
    }

    public function create(int $customerId,array $data): Review
    {
        return DB::transaction(function()use($customerId,$data){
            $hotel=Hotel::where('status','active')->whereKey($data['hotel_id'])->lockForUpdate()->firstOrFail();
            Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();
            $booking=Booking::whereKey($data['booking_id'])->where('customer_id',$customerId)
                ->where('hotel_id',$hotel->id)->lockForUpdate()->first();
            $message='Chỉ chủ đơn đã thanh toán thành công mới được gửi đánh giá.';
            abort_unless($booking && in_array($booking->status,['confirmed','completed'],true),422,$message);
            $paid=Payment::where('booking_id',$booking->id)->where('payment_status','success')
                ->where('amount',$booking->total_price)->lockForUpdate()->first();
            abort_unless($paid,422,$message);
            abort_if(Review::where('customer_id',$customerId)->where('hotel_id',$hotel->id)->exists(),422,'Bạn đã gửi đánh giá cho khách sạn này.');
            $review=Review::create([
                'customer_id'=>$customerId,'booking_id'=>$booking->id,'hotel_id'=>$hotel->id,
                'rating'=>$data['rating'],'status'=>'pending',
                'rating_cleanliness'=>$data['rating_cleanliness']??null,'rating_service'=>$data['rating_service']??null,
                'rating_location'=>$data['rating_location']??null,'comment'=>$data['comment']??null,
                'helpful_count'=>0,'created_at'=>now(),
            ]);
            $this->recalcAvgRating($hotel->id);
            CustomerNotification::reviewSubmitted($customerId,$review->id,$hotel->name,$review->rating);
            return $review->load('customer:id,name,avatar_url');
        },3);
    }

    public function delete(int $id,?int $customerId=null): void
    {
        $candidate=Review::findOrFail($id);
        DB::transaction(function()use($candidate,$id,$customerId){
            if($candidate->hotel_id)Hotel::whereKey($candidate->hotel_id)->lockForUpdate()->firstOrFail();
            $review=Review::whereKey($id)->when($customerId!==null,fn($q)=>$q->where('customer_id',$customerId))->lockForUpdate()->firstOrFail();
            $hotelId=$review->hotel_id;$review->delete();
            if($hotelId)$this->recalcAvgRating($hotelId);
        },3);
    }

    public function moderate(int $id,string $status): Review
    {
        $candidate=Review::findOrFail($id);
        return DB::transaction(function()use($candidate,$id,$status){
            if($candidate->hotel_id)Hotel::whereKey($candidate->hotel_id)->lockForUpdate()->firstOrFail();
            $review=Review::whereKey($id)->lockForUpdate()->firstOrFail();
            if($review->status===$status)return $review;
            $review->update(['status'=>$status]);
            if($review->hotel_id)$this->recalcAvgRating($review->hotel_id);
            if($status==='approved')CustomerNotification::create([
                'customer_id'=>$review->customer_id,'type'=>'review_approved',
                'data'=>['title'=>'Đánh giá đã được duyệt','message'=>'Đánh giá của bạn đã được hiển thị trên trang khách sạn.','review_id'=>$review->id,'hotel_id'=>$review->hotel_id,'link'=>$review->hotel_id?'/hotels/'.$review->hotel_id.'#hotel-reviews':'/bookings'],
            ]);
            return $review;
        },3);
    }

    public function myReviews(int $customerId): \Illuminate\Database\Eloquent\Collection
    {
        return Review::with($this->relations(['hotel:id,name,star_rating']))->where('customer_id',$customerId)->orderByDesc('id')->get();
    }

    public function incrementHelpful(int $id): void
    {
        Review::published()->findOrFail($id)->increment('helpful_count');
    }

    private function recalcAvgRating(int $hotelId): void
    {
        $avg=Review::published()->where('hotel_id',$hotelId)->avg('rating')??0;
        Hotel::whereKey($hotelId)->update(['avg_rating'=>round($avg,1)]);
    }

    private function relations(array $relations): array
    {
        if(DB::getSchemaBuilder()->hasTable('review_image'))$relations[]='images';
        return $relations;
    }
}
