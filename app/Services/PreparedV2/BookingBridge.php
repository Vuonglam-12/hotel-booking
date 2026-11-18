<?php

namespace App\Services\PreparedV2;

use App\Models\Booking;
use App\Services\BookingService;
use App\Services\BookingServiceV2;
use App\Services\BookingLifecycleServiceV2;

/** Preserve legacy read contracts; route any container-resolved booking mutations through V2. */
class BookingBridge extends BookingService
{
    public function getDetail(int $id,int $customerId): Booking
    {
        if (config('roomtype-v2.final_integration',false)) return app(\App\Services\BookingReadServiceV2::class)->detail($id,$customerId);
        return parent::getDetail($id,$customerId);
    }
    public function myBookings(int $customerId,array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        if (config('roomtype-v2.final_integration',false)) return app(\App\Services\BookingReadServiceV2::class)->listing($filters,$customerId);
        return parent::myBookings($customerId,$filters);
    }
    public function create(int $customerId, array $data): Booking
    {
        $row = app(BookingServiceV2::class)->create('mysql', $customerId, $data);
        return Booking::findOrFail($row['id']);
    }

    public function cancel(int $id, int $customerId): Booking
    {
        app(BookingLifecycleServiceV2::class)->cancel('mysql', $id, $customerId);
        return Booking::findOrFail($id);
    }
}
