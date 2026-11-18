<?php

namespace App\Models\Concerns;

use App\Support\AccommodationImage;

trait HasAccommodationImages
{
    public function getGalleryImageUrlsAttribute(): array
    {
        $images = $this->relationLoaded('images')
            ? $this->images
            : $this->images()->orderByDesc('is_primary')->orderBy('id')->get();

        $sort = $images->first() && isset($images->first()->sort_order) ? [['sort_order', 'asc'], ['id', 'asc']] : [['is_primary', 'desc'], ['id', 'asc']];
        return $images->sortBy($sort)
            ->map(fn($image) => AccommodationImage::localUrl($image->image_url))
            ->filter()->unique()->values()->all();
    }

    public function getCoverImageUrlAttribute(): string
    {
        return AccommodationImage::cover($this->relationLoaded('images') ? $this->images : $this->images()->orderBy('id')->get());
    }
}
