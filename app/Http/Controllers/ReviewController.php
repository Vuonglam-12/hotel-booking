<?php

namespace App\Http\Controllers;

use App\Services\ReviewService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $service) {}

    public function hotelReviews(Request $request,int $hotel_id)
    {
        $data=$request->validate(['per_page'=>'sometimes|integer|between:1,100','page'=>'sometimes|integer|min:1|max:100000',
            'rating'=>'nullable|integer|between:1,5','sort'=>'sometimes|in:newest,highest,lowest']);
        return response()->json($this->service->getHotelReviews($hotel_id,$data));
    }

    public function store(Request $request)
    {
        $data=$request->validate([
            'hotel_id'=>'required|integer|exists:hotel,id','booking_id'=>'required|integer|exists:booking,id',
            'rating'=>'required|integer|between:1,5','rating_cleanliness'=>'nullable|integer|between:1,5',
            'rating_service'=>'nullable|integer|between:1,5','rating_location'=>'nullable|integer|between:1,5',
            'comment'=>'nullable|string|max:2000','status'=>'prohibited',
        ]);
        return response()->json(['message'=>'Đã gửi đánh giá. Nội dung đang chờ Admin duyệt.','review'=>$this->service->create((int)auth('sanctum')->id(),$data)],201);
    }

    public function destroy(int $id)
    {
        $this->service->delete($id,(int)auth('sanctum')->id());
        return response()->json(['message'=>'Đã xóa đánh giá của bạn.']);
    }

    public function helpful(int $id)
    {
        $this->service->incrementHelpful($id);
        return response()->json(['message'=>'Cảm ơn phản hồi của bạn!']);
    }

    public function myReviews()
    {
        return response()->json($this->service->myReviews((int)auth('sanctum')->id()));
    }
}
