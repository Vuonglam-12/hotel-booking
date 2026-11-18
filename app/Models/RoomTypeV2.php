<?php

namespace App\Models;

use App\Support\RoomTypeV2Database;

/** Same table with a guarded V2 connection; legacy model/relations remain unchanged. */
class RoomTypeV2 extends RoomType
{
    protected $fillable = ['name', 'capacity', 'base_price', 'bed_type', 'description', 'hotel_id', 'total_rooms', 'status'];
    protected $casts = ['base_price' => 'decimal:2', 'capacity' => 'integer', 'total_rooms' => 'integer', 'hotel_id' => 'integer'];

    public function getConnection()
    {
        return RoomTypeV2Database::connection($this->getConnectionName() ?? config('database.default'));
    }

    public function hotel() { return $this->belongsTo(Hotel::class, 'hotel_id'); }
    public function images() { return $this->hasMany(RoomTypeImage::class, 'room_type_id')->orderByDesc('is_primary')->orderBy('id'); }
}
