<?php

namespace App\Services\Admin;

use App\Models\GuideCategory;
use App\Models\GuidePost;
use App\Models\Location;
use App\Services\AdminAccommodationImageService;
use App\Support\AccommodationImage;
use App\Support\GuideHtml;
use App\Support\GuideSchema;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GuideService
{
    public function __construct(private GuideSchema $schema, private GuideHtml $html, private AdminAccommodationImageService $images) {}

    public function metadata(): array
    {
        $this->schema->ensureReady();
        return ['categories' => GuideCategory::withCount('posts')->orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(['id', 'name']), 'image_max_bytes' => AdminAccommodationImageService::MAX_KB * 1024];
    }

    public function listing(array $filters): array
    {
        $this->schema->ensureReady();
        $query = GuidePost::with(['category', 'location']);
        if (($filters['archived'] ?? false)) $query->whereNotNull('archived_at'); else $query->whereNull('archived_at');
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['category_id'])) $query->where('category_id', $filters['category_id']);
        if (!empty($filters['q'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
            $query->where('title', 'like', $term);
        }
        $page = $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(12, ['*'], 'page', $filters['page'] ?? 1);
        return ['data' => $page->getCollection()->map(fn ($post) => $this->present($post))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]];
    }

    private function present(GuidePost $post): array
    {
        return [...$post->toArray(), 'body_html' => $this->html->clean($post->body_html),
            'cover_url' => AccommodationImage::localUrl($post->cover_path), 'legacy_import_state' => $post->legacy_import_state,
            'public_url' => route('guides.show', $post->slug)];
    }

    public function detail(int $id): array
    {
        $this->schema->ensureReady();
        return $this->present(GuidePost::with(['category', 'location'])->findOrFail($id));
    }

    public function save(?int $id, array $data, ?UploadedFile $cover): array
    {
        $this->schema->ensureReady();
        return $this->images->atomic(DB::connection(), function ($uploads) use ($id, $data, $cover) {
            $post = $id ? GuidePost::lockForUpdate()->findOrFail($id) : new GuidePost();
            abort_if($post->archived_at, 409, 'Khôi phục bài viết trước khi chỉnh sửa.');
            if ($cover && ($data['remove_cover'] ?? false)) throw ValidationException::withMessages(['cover' => 'Chọn thay ảnh hoặc xóa ảnh bìa trong một lần lưu.']);
            if (!empty($data['published_at'])) $data['published_at'] = Carbon::parse($data['published_at'])->timezone(config('app.timezone'))->toDateTimeString();
            $post->fill(collect($data)->only(['title', 'excerpt', 'category_id', 'location_id', 'author_name', 'status', 'is_featured', 'published_at'])->all());
            $post->body_html = $this->html->clean($data['body_html'] ?? '');
            if (!$post->slug) $post->slug = rtrim(Str::limit(Str::slug($post->title) ?: 'cam-nang', 180, ''), '-').'-'.strtolower(Str::ulid());
            if ($post->status === 'published') {
                $errors = [];
                if (mb_strlen($this->html->text($post->body_html)) < 100) $errors['body_html'] = 'Bài xuất bản cần ít nhất 100 ký tự nội dung thực sau khi lọc HTML.';
                if (!trim((string) $post->excerpt)) $errors['excerpt'] = 'Bổ sung tóm tắt trước khi xuất bản.';
                if (!trim((string) $post->author_name)) $errors['author_name'] = 'Điền tên tác giả thực trước khi xuất bản.';
                if (!GuideCategory::whereKey($post->category_id)->where('is_active', true)->exists()) $errors['category_id'] = 'Chọn danh mục đang hiển thị trước khi xuất bản.';
                if ($errors) throw ValidationException::withMessages($errors);
                $post->published_at ??= now();
            }
            if ($data['remove_cover'] ?? false) $post->cover_path = null;
            $post->save();
            if ($cover) {
                $directory = 'guides/'.$post->id; $path = $directory.'/'.$cover->hashName(); $uploads[] = $path;
                if (!$cover->storeAs($directory, basename($path), 'public')) throw new \RuntimeException('Không thể lưu ảnh bìa.');
                $post->cover_path = 'storage/'.$path; $post->save();
            }
            // Replaced assets stay on disk; legacy images are never deleted.
            return $this->detail($post->id);
        });
    }

    public function archive(int $id): void
    {
        $this->schema->ensureReady(); GuidePost::findOrFail($id)->update(['status' => 'draft', 'archived_at' => now()]);
    }
    public function restore(int $id): void
    {
        $this->schema->ensureReady(); GuidePost::findOrFail($id)->update(['status' => 'draft', 'archived_at' => null]);
    }

    public function saveCategory(?int $id, array $data): GuideCategory
    {
        $this->schema->ensureReady();
        return DB::transaction(function () use ($id, $data) {
            $category = $id ? GuideCategory::lockForUpdate()->findOrFail($id) : new GuideCategory();
            $category->name = $data['name']; $category->is_active = $data['is_active'];
            if (!$category->slug) $category->slug = rtrim(Str::limit(Str::slug($category->name) ?: 'danh-muc', 100, ''), '-').'-'.strtolower(Str::ulid());
            $category->save(); return $category;
        });
    }
    public function deleteCategory(int $id): void
    {
        $this->schema->ensureReady();
        DB::transaction(function () use ($id) {
            $category = GuideCategory::lockForUpdate()->findOrFail($id);
            if ($category->posts()->exists()) throw ValidationException::withMessages(['category_id' => 'Danh mục còn bài viết. Chuyển bài sang danh mục khác hoặc ẩn danh mục.']);
            $category->delete();
        });
    }
}
