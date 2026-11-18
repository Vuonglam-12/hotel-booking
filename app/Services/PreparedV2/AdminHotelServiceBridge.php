<?php

namespace App\Services\PreparedV2;

class AdminHotelServiceBridge extends \App\Services\Admin\HotelService
{
    public function getList(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return app(\App\Services\HotelCatalogServiceV2::class)->query($filters,false)->orderByDesc('id')->paginate($filters['per_page']??15);
    }
    public function getRoomTypes(int $hotelId): \Illuminate\Database\Eloquent\Collection
    {
        \App\Support\RoomTypeV2Inventory::open('mysql'); return \App\Models\RoomTypeV2::where('hotel_id',$hotelId)->get();
    }
    public function createRoomType(int $hotelId,array $data): array
    {
        return ['room_type'=>app(\App\Services\RoomTypeAdminServiceV2::class)->save('mysql',$hotelId,null,$data),'rooms_created'=>0];
    }
}
