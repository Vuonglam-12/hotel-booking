<?php

namespace App\Models;

use App\Support\RoomTypeV2Database;
use Illuminate\Database\Eloquent\Model;

class RoomTypeImage extends Model
{
    protected $table = 'room_type_image';
    public $timestamps = false;
    protected $fillable = ['room_type_id', 'image_url', 'is_primary', 'created_at'];
    protected $casts = ['is_primary' => 'boolean'];

    public function getConnection()
    {
        return RoomTypeV2Database::connection($this->getConnectionName() ?? config('database.default'));
    }

    public function roomType() { return $this->belongsTo(RoomTypeV2::class, 'room_type_id'); }
}
