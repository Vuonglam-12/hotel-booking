<?php

namespace App\Services;

use App\Models\GuidePost;
use App\Models\GuideCategory;
use App\Support\AccommodationImage;
use App\Support\ExperienceSchema;
use App\Support\GuideHtml;
use App\Support\GuideSchema;
use Illuminate\Database\Eloquent\Builder;

final class GuideCatalogService
{
    public function __construct(private GuideSchema $schema, private GuideHtml $html, private ExperienceCatalogService $experiences) {}

    public function publicQuery(): Builder
    {
        $this->schema->ensureReady();
        return GuidePost::query()->where('status', 'published')->whereNull('archived_at')->where('published_at', '<=', now())
            ->whereNotNull('body_html')->where('body_html', '<>', '')->whereHas('category', fn ($q) => $q->where('is_active', true));
    }

    public function listing(array $filters): array
    {
        $query = $this->publicQuery()->with(['category', 'location']);
        if (!empty($filters['category'])) $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        if (!empty($filters['q'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
            $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('excerpt', 'like', $term));
        }
        $page = $query->orderByDesc('published_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 9, ['*'], 'page', $filters['page'] ?? 1);
        $featured = empty($filters['q']) && empty($filters['category']) && ($filters['page'] ?? 1) == 1
            ? $this->publicQuery()->with(['category', 'location'])->where('is_featured', true)->orderByDesc('published_at')->orderByDesc('id')->first() : null;
        return ['data' => $page->getCollection()->map(fn ($post) => $this->present($post))->all(),
            'featured' => $featured ? $this->present($featured) : null,
            'categories' => GuideCategory::where('is_active', true)->orderBy('name')->get(['name', 'slug']),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]];
    }

    public function find(string $slug): GuidePost
    {
        return $this->publicQuery()->with(['category', 'location'])->where('slug', $slug)->firstOrFail();
    }

    public function present(GuidePost $post, bool $detail = false): array
    {
        $result = ['id' => $post->id, 'slug' => $post->slug, 'title' => $post->title, 'excerpt' => $post->excerpt,
            'url' => route('guides.show', $post->slug), 'cover_url' => AccommodationImage::localUrl($post->cover_path),
            'category' => ['name' => $post->category->name, 'slug' => $post->category->slug], 'author' => $post->author_name,
            'published_at' => $post->published_at?->toIso8601String(), 'published_label' => $post->published_at?->format('d/m/Y'),
            'location' => $post->location ? ['id' => $post->location->id, 'name' => $post->location->name] : null];
        if ($detail) {
            $related = $this->publicQuery()->with(['category', 'location'])->where('id', '<>', $post->id)
                ->where(fn ($q) => $q->where('category_id', $post->category_id)->when($post->location_id, fn ($q) => $q->orWhere('location_id', $post->location_id)))
                ->orderByDesc('published_at')->limit(3)->get();
            $suggestions = [];
            if ($post->location_id && app(ExperienceSchema::class)->ready()) $suggestions = $this->experiences->publicQuery()
                ->with(['location', 'images'])->where('location_id', $post->location_id)->orderByDesc('experience_published_at')->limit(3)->get()
                ->map(fn ($item) => $this->experiences->present($item))->all();
            $result += ['body_html' => $this->html->clean($post->body_html), 'related' => $related->map(fn ($item) => $this->present($item))->all(),
                'experiences' => $suggestions, 'hotels' => $post->location_id ? $this->experiences->hotels($post->location_id) : []];
        }
        return $result;
    }
}
