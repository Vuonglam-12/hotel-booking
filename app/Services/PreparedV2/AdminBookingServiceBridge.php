<?php

namespace App\Services\PreparedV2;

class AdminBookingServiceBridge extends \App\Services\Admin\BookingService
{
    public function getList(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator { return app(\App\Services\BookingReadServiceV2::class)->listing($filters); }
    public function updateStatus(int $id,string $status): array { return app(\App\Services\BookingLifecycleServiceV2::class)->transition('mysql',$id,$status,(int)auth('admin')->id()); }
}
