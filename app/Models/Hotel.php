<?php
namespace App\Models;

use App\Models\Concerns\HasAccommodationImages;
use Illuminate\Database\Eloquent\Model;
class Hotel extends Model
{
    use HasAccommodationImages;

    protected $table ='hotel';

    public $timestamps = false;

    protected $fillable =[
        'location_id',
        'name',
        'phone',
        'email',
        'address',
        'latitude',
        'longitude',
        'google_place_id',
        'star_rating',
        'description',
        'status',
        'avg_rating',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
    
       public function amenities()
    {
        return $this->hasMany(HotelAmenity::class);
    }
 
    public function images()
    {
        return $this->hasMany(HotelImage::class);
    }
 
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
 
    // Giá phòng thấp nhất
    public function minPrice()
    {
        return $this->rooms()
            ->where('status', 'available')
            ->offered()
            ->min('price');
    }

}
?>
