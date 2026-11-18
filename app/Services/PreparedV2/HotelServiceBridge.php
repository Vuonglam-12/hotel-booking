<?php

namespace App\Services\PreparedV2;

/** Equivalent read methods for the legacy Hotel service namespace, without physical Room access. */
class HotelServiceBridge extends \App\Services\Hotel\HotelService
{
    public function getList(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator { return app(\App\Services\HotelCatalogServiceV2::class)->getList($filters); }
    public function getDetail(int $id): \App\Models\Hotel { return app(\App\Services\HotelCatalogServiceV2::class)->getDetail($id); }
    public function getRooms(int $hotelId,array $filters): \Illuminate\Support\Collection { return app(\App\Services\HotelCatalogServiceV2::class)->getRooms($hotelId,$filters); }
    public function getLocations(): \Illuminate\Database\Eloquent\Collection { return app(\App\Services\HotelCatalogServiceV2::class)->getLocations(); }
}
