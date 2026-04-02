<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewImage extends Model
{
    protected $table = 'review_image';

    public $timestamps = false;

    protected $fillable = [
        'review_id',
        'image_url',
        'created_at',
    ];

    // Quan hệ: ảnh thuộc về review nào
    public function review()
    {
        return $this->belongsTo(Review::class);
    }
}