<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Services\HotelRoomTypeService;
use App\Services\RoomPricingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class RoomPricingController extends Controller
{
    public function quote(Request $request, int $hotel, int $type, RoomPricingService $pricing)
    {
        $data = $request->validate(['check_in' => 'required|date_format:Y-m-d|after_or_equal:' . CarbonImmutable::today('Asia/Ho_Chi_Minh')->toDateString(),
            'check_out' => 'required|date_format:Y-m-d|after:check_in', 'quantity' => 'sometimes|integer|between:1,10']);
        abort_unless(Hotel::whereKey($hotel)->where('status', 'active')->exists(), 404);
        abort_if(in_array(app(HotelRoomTypeService::class)->effective($hotel, $type)->status, ['inactive', 'archived'], true), 404);
        return response()->json($pricing->quote($hotel, $type, $data['check_in'], $data['check_out'], (int) ($data['quantity'] ?? 1)))
            ->header('Cache-Control', 'no-store');
    }

    public function index(int $hotel, int $type, RoomPricingService $pricing)
    {
        $rates=$pricing->rates($hotel,$type);
        $roomType=app(HotelRoomTypeService::class)->effective($hotel,$type);
        return response()->json(['rates'=>$rates,'context'=>[
            'hotel_id'=>$hotel,'hotel_name'=>Hotel::findOrFail($hotel)->name,
            'room_type_id'=>$type,'room_type_name'=>$roomType->name,'base_price'=>$roomType->base_price,
        ]])->header('Cache-Control','no-store');
    }

    public function store(Request $request, int $hotel, int $type, RoomPricingService $pricing)
    {
        return response()->json(['message' => 'Đã thêm giá phòng.', 'rate' => $pricing->saveRate($hotel, $type, $request->all())], 201);
    }

    public function update(Request $request, int $hotel, int $type, int $rate, RoomPricingService $pricing)
    {
        return response()->json(['message' => 'Đã cập nhật giá phòng.', 'rate' => $pricing->saveRate($hotel, $type, $request->all(), $rate)]);
    }

    public function destroy(int $hotel, int $type, int $rate, RoomPricingService $pricing)
    {
        $pricing->deleteRate($hotel, $type, $rate);
        return response()->json(['message' => 'Đã xóa mức giá. Giá trong đơn đã đặt được giữ nguyên.']);
    }
}
