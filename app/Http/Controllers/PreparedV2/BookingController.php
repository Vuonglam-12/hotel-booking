<?php

namespace App\Http\Controllers\PreparedV2;

use App\Http\Controllers\Controller;
use App\Services\BookingServiceV2;
use App\Services\BookingLifecycleServiceV2;
use App\Support\RoomTypeV2Inventory;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function store(Request $r,BookingServiceV2 $service)
    {
        $customer=(int)$r->user('sanctum')->id;
        if (config('roomtype-v2.final_integration',false)) {
            $r->merge(['payment_method'=>$r->input('payment_method','banking')]);
            $r->validate(['payment_method'=>'required|in:banking,vnpay']);
        }
        \App\Support\RoomTypeV2CheckoutGate::assertWriteAllowed();
        $booking=$service->create('mysql',$customer,$r->all());
        if (config('roomtype-v2.integration_complete',false)) $booking=app(\App\Services\BookingService::class)->getDetail($booking['id'],$customer);
        return response()->json(['booking'=>$booking],201);
    }
    public function cancel(Request $r,int $id,BookingLifecycleServiceV2 $service) { return response()->json(['booking'=>$service->cancel('mysql',$id,(int)$r->user('sanctum')->id)]); }
    public function status(Request $r,int $id,BookingLifecycleServiceV2 $service)
    {
        $data=$r->validate(['status'=>'required|in:pending,confirmed,completed,cancelled']);
        return response()->json(['booking'=>$service->transition('mysql',$id,$data['status'],(int)$r->user('admin')->id)]);
    }
    public function show(Request $r,int $id)
    {
        RoomTypeV2Inventory::open('mysql');
        // Existing history adapter already reads nullable room_id, quantities and stored snapshots.
        return response()->json(['booking'=>app(\App\Services\BookingService::class)->getDetail($id,(int)$r->user('sanctum')->id)]);
    }
    public function mine(Request $r)
    {
        RoomTypeV2Inventory::open('mysql');
        return response()->json(app(\App\Services\BookingService::class)->myBookings((int)$r->user('sanctum')->id,$r->all()));
    }
}
