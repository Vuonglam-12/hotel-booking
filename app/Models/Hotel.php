<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Hotel extends Model
{
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
        'check_in_time',
        'check_out_time',
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
            ->min('price');
    }

    public function getCoverImageUrlAttribute(): string
    {
        $img = $this->relationLoaded('images')
            ? ($this->images->firstWhere('is_primary', true) ?? $this->images->first())
            : $this->images()->orderByDesc('is_primary')->first();

        if (!$img) {
            return "https://picsum.photos/seed/{$this->id}/800/600"; // fallback khi chưa gán ảnh thật
        }

        return asset(dirname($img->image_url) . '/' . rawurlencode(basename($img->image_url)));
    }
}
?>