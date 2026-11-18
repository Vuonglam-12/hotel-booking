<?php

namespace App\Services;

use App\Models\Attraction;
use App\Models\Hotel;
use App\Models\Location;
use App\Support\AccommodationImage;
use App\Support\ExperienceCategory;
use App\Support\ExperienceSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class ExperienceCatalogService
{
    public function __construct(private ExperienceSchema $schema) {}

    public function publicQuery(): Builder
    {
        $this->schema->ensureReady();
        return Attraction::query()->where('is_active', true)->whereNull('experience_archived_at')
            ->whereNotNull('slug')->whereNotNull('experience_verified_at')->where('experience_published_at', '<=', now())
            ->whereHas('location', fn (Builder $q) => $q->whereNotNull('code')
                ->whereColumn('location.code', 'attraction.experience_location_code'))
            ->where(function (Builder $query) {
                foreach (ExperienceCategory::TYPES as $category => $type) {
                    $query->orWhere(fn (Builder $q) => $q->where('experience_category', $category)->where('item_type', $type));
                }
            });
    }

    public function listing(array $filters): array
    {
        $query = $this->publicQuery()->with(['location', 'images']);
        if (!empty($filters['location'])) $query->whereHas('location', fn ($q) => $q->where('code', $filters['location']));
        if (!empty($filters['category'])) $query->where('experience_category', $filters['category']);
        if (!empty($filters['q'])) {
            $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']);
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('experience_summary', 'like', '%'.$term.'%'));
        }
        if (($filters['sort'] ?? '') === 'name') $query->orderBy('name');
        else $query->orderByDesc('experience_published_at');
        $page = $query->orderByDesc('id')->paginate($filters['per_page'] ?? 12, ['*'], 'page', $filters['page'] ?? 1);
        return ['data' => $page->getCollection()->map(fn ($item) => $this->present($item))->values()->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()]];
    }

    public function filters(): array
    {
        $this->schema->ensureReady();
        $eligibleIds = $this->publicQuery()->select('location_id');
        $locations = Location::whereIn('id', $eligibleIds)->orderBy('name')->get(['id', 'name', 'code']);
        return ['locations' => $locations, 'categories' => collect(ExperienceCategory::LABELS)
            ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()];
    }

    public function find(string $slug): Attraction
    {
        return $this->publicQuery()->with(['location', 'images'])->where('slug', $slug)->firstOrFail();
    }

    /** Explicit public fields: never serialize AI estimates, ticket prices, ratings or private verification metadata. */
    public function present(Attraction $item, bool $detail = false): array
    {
        $gallery = $item->images->map(function ($image) use ($item) {
            $url = AccommodationImage::localUrl($image->image_url);
            return $url && $image->source_note ? ['url' => $url, 'alt' => $image->caption ?: $item->name, 'source' => $image->source_note] : null;
        })->filter()->values()->all();
        $result = ['id' => $item->id, 'slug' => $item->slug, 'name' => $item->name,
            'url' => route('experiences.show', $item->slug),
            'location' => ['name' => $item->location->name, 'code' => $item->location->code],
            'category' => ['key' => $item->experience_category, 'label' => ExperienceCategory::LABELS[$item->experience_category]],
            'summary' => $item->experience_summary ?: Str::limit(strip_tags($item->description ?? ''), 160),
            'cover' => $gallery[0] ?? null];
        if ($detail) $result += ['description' => $item->description, 'address' => $item->address,
            'duration_minutes' => $item->experience_duration_minutes,
            'open_time' => $item->open_time, 'close_time' => $item->close_time,
            'source_url' => $item->experience_source_url, 'gallery' => $gallery,
            'hotels' => $this->hotels($item->location_id)];
        return $result;
    }

    public function hotels(int $locationId): array
    {
        return Hotel::with(['images'])->where('location_id', $locationId)->where('status', 'active')
            ->orderBy('name')->limit(4)->get()->map(fn ($hotel) => [
                'id' => $hotel->id, 'name' => $hotel->name, 'address' => $hotel->address,
                'url' => route('hotel.detail', $hotel->id), 'cover_url' => $hotel->cover_image_url,
            ])->all();
    }
}
