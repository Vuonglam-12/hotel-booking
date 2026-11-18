<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttractionImage extends Model
{
    protected $table = 'attraction_image';
    public $timestamps = false;
    protected $fillable = ['attraction_id', 'image_url', 'is_primary', 'caption', 'source_note'];
    protected $casts = ['is_primary' => 'boolean'];

    public function attraction(): BelongsTo
    {
        return $this->belongsTo(Attraction::class, 'attraction_id');
    }
}
