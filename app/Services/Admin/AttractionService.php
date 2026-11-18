<?php

namespace App\Services\Admin;

use App\Models\Attraction;
use App\Models\Location;
use App\Services\AdminAccommodationImageService;
use App\Support\AccommodationImage;
use App\Support\ExperienceCategory;
use App\Support\ExperienceSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AttractionService
{
    public function __construct(private ExperienceSchema $schema, private AdminAccommodationImageService $images) {}

    public function getByLocation(int $locationId): array
    {
        $this->schema->ensureReady();
        Location::findOrFail($locationId);
        return Attraction::with(['location', 'images'])->where('location_id', $locationId)->whereNull('experience_archived_at')
            ->orderBy('item_type')->orderBy('name')->get()->map(fn ($item) => $this->present($item))->all();
    }

    public function listing(array $filters): array
    {
        $this->schema->ensureReady();
        $query = Attraction::with(['location', 'images']);
        if (empty($filters['archived'])) $query->whereNull('experience_archived_at');
        else $query->whereNotNull('experience_archived_at');
        if (!empty($filters['location_id'])) $query->where('location_id', $filters['location_id']);
        if (!empty($filters['q'])) $query->where('name', 'like', '%'.$filters['q'].'%');
        $page = $query->orderByDesc('id')->paginate(12, ['*'], 'page', $filters['page'] ?? 1);
        return ['data' => $page->getCollection()->map(fn ($item) => $this->present($item))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]];
    }

    public function detail(int $id): array
    {
        $this->schema->ensureReady();
        return $this->present(Attraction::with(['location', 'images'])->findOrFail($id));
    }

    public function save(?int $id, array $data, array $changes, array $files, int $adminId): array
    {
        $this->schema->ensureReady();
        $item = $this->images->atomic(DB::connection(), function ($uploads) use ($id, $data, $changes, $files, $adminId) {
            $item = $id ? Attraction::lockForUpdate()->findOrFail($id) : new Attraction(['is_active' => false]);
            if ($item->experience_archived_at) $this->invalid('name', 'Khôi phục trải nghiệm trước khi chỉnh sửa.');
            $location = Location::findOrFail($data['location_id'] ?? $item->location_id);
            if (isset($data['slug']) && $item->slug && $data['slug'] !== $item->slug) {
                $this->invalid('slug', 'Đường dẫn đã tạo được giữ cố định để bảo toàn liên kết.');
            }
            $fields = collect($data)->except(['verify', 'publish', 'image_source', 'image_caption'])->all();
            $item->fill($fields);
            $item->location_id = $location->id;
            if (!$item->slug) $item->slug = (Str::limit(Str::slug($item->name), 145, '') ?: 'trai-nghiem').'-'.Str::lower((string) Str::ulid());
            if (Attraction::where('slug', $item->slug)->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
                $this->invalid('slug', 'Đường dẫn này đã được sử dụng.');
            }
            $material = ['location_id', 'name', 'item_type', 'description', 'address', 'experience_category',
                'experience_summary', 'experience_duration_minutes', 'experience_source_url', 'open_time', 'close_time', 'lat', 'lng'];
            if (!$id || $item->isDirty($material) || $changes || $files) {
                $item->experience_verified_at = null;
                $item->experience_verified_by = null;
                $item->experience_location_code = null;
                $item->experience_published_at = null;
            }
            $item->save();
            $before = $item->images()->pluck('id')->all();
            $remaining = count($before) - count($changes['delete_ids'] ?? []) + count($files);
            if ($remaining > 12) $this->invalid('images', 'Mỗi trải nghiệm tối đa 12 ảnh.');
            $this->images->apply(DB::connection(), 'attraction_image', 'attraction_id', $item->id, $changes, $files, $uploads);
            if ($files) $item->images()->whereNotIn('id', $before)->update([
                'source_note' => $data['image_source'], 'caption' => $data['image_caption'] ?? null,
            ]);
            if (!empty($data['verify'])) {
                if (!$location->code || !$item->experience_source_url) {
                    $this->invalid('verify', 'Cần mã địa phương ổn định và đường dẫn nguồn thông tin trước khi xác minh.');
                }
                if ((ExperienceCategory::TYPES[$item->experience_category] ?? null) !== $item->item_type) {
                    $this->invalid('experience_category', 'Danh mục trải nghiệm phải phù hợp loại địa điểm.');
                }
                $item->experience_verified_at = now();
                $item->experience_verified_by = $adminId;
                $item->experience_location_code = $location->code;
            }
            if (array_key_exists('publish', $data)) {
                if ($data['publish']) {
                    if (!$item->experience_verified_at || $item->experience_location_code !== $location->code || !$item->is_active) {
                        $this->invalid('publish', 'Chỉ xuất bản khi đã xác minh đúng địa phương và bật trạng thái hoạt động.');
                    }
                    $item->experience_published_at ??= now();
                } else $item->experience_published_at = null;
            }
            $item->save();
            return $item;
        });
        return $this->present($item->load(['location', 'images']));
    }

    public function toggle(int $id): array
    {
        $this->schema->ensureReady();
        return DB::transaction(function () use ($id) {
            $item = Attraction::lockForUpdate()->findOrFail($id);
            if ($item->experience_archived_at) $this->invalid('is_active', 'Không thể bật mục đã lưu trữ.');
            $item->update(['is_active' => !$item->is_active]);
            return ['is_active' => $item->is_active];
        });
    }

    /** Archive instead of deleting shared AI data, historical references or uploaded files. */
    public function archive(int $id): void
    {
        $this->schema->ensureReady();
        Attraction::findOrFail($id)->update(['is_active' => false, 'experience_published_at' => null, 'experience_archived_at' => now()]);
    }

    public function restore(int $id): void
    {
        $this->schema->ensureReady();
        Attraction::findOrFail($id)->update(['is_active' => false, 'experience_published_at' => null,
            'experience_archived_at' => null, 'experience_verified_at' => null, 'experience_verified_by' => null, 'experience_location_code' => null]);
    }

    private function present(Attraction $item): array
    {
        $result = $item->toArray();
        $result['images'] = $item->images->map(fn ($image) => ['id' => $image->id, 'is_primary' => $image->is_primary,
            'caption' => $image->caption, 'source_note' => $image->source_note,
            'display_url' => AccommodationImage::localUrl($image->image_url)])->all();
        return $result;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
