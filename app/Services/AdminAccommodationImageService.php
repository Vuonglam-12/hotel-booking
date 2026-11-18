<?php

namespace App\Services;

use App\Support\AccommodationImage;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Batch changes to existing galleries. Removing a row never removes its asset. */
final class AdminAccommodationImageService
{
    public const MAX_KB = 2048;
    public const MAX_FILES = 20;
    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public static function limits(): array
    {
        return ['max_bytes' => self::MAX_KB * 1024, 'max_files' => self::MAX_FILES,
            'mime_types' => self::MIME_TYPES, 'label' => 'JPG, PNG, WebP · tối đa 2 MB/ảnh · 20 ảnh mới/lần lưu'];
    }

    public function input(Request $request): array
    {
        $changes = $request->input('image_changes', []);
        if (is_string($changes)) {
            try { $changes = json_decode($changes, true, 512, JSON_THROW_ON_ERROR); }
            catch (\JsonException $e) { throw ValidationException::withMessages(['image_changes' => 'Thay đổi ảnh không hợp lệ.']); }
        }
        $data = Validator::make(['image_changes' => $changes, 'images' => $request->file('images', [])], [
            'image_changes' => 'present|array:delete_ids,primary_id,primary_new,order',
            'image_changes.delete_ids' => 'sometimes|array',
            'image_changes.delete_ids.*' => 'required|integer|min:1|distinct',
            'image_changes.primary_id' => 'sometimes|integer|min:1',
            'image_changes.primary_new' => 'sometimes|integer|min:0',
            'image_changes.order' => 'sometimes|array',
            'image_changes.order.*' => ['required', 'string', 'distinct', 'regex:/^(s-[1-9]\d*|n-\d+)$/D'],
            'images' => 'array|max:' . self::MAX_FILES,
            'images.*' => ['bail', 'required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:' . self::MAX_KB,
                function ($attribute, $file, $fail) { if (@getimagesize($file->getRealPath()) === false) $fail('File không phải ảnh JPG, PNG hoặc WebP hợp lệ.'); }],
        ], ['images.max' => 'Tối đa 20 ảnh mới mỗi lần lưu.', 'images.*.max' => 'Mỗi ảnh tối đa 2 MB.',
            'images.*.image' => 'Vui lòng chọn ảnh JPG, PNG hoặc WebP hợp lệ.',
            'images.*.mimes' => 'Chỉ hỗ trợ ảnh JPG, PNG và WebP.'])->validate();
        $changes = $data['image_changes'];
        if (isset($changes['primary_id'], $changes['primary_new'])) {
            throw ValidationException::withMessages(['image_changes' => 'Chỉ chọn một ảnh đại diện.']);
        }
        $files = array_values($data['images']);
        if (isset($changes['primary_new']) && !isset($files[$changes['primary_new']])) {
            throw ValidationException::withMessages(['image_changes.primary_new' => 'Ảnh đại diện mới không tồn tại.']);
        }
        return [$changes, $files];
    }

    /** Keep form fields and links atomic; clean up only uploads created by this failed operation. */
    public function atomic(Connection $db, callable $callback): mixed
    {
        $uploads = new \ArrayObject();
        try { return $db->transaction(fn() => $callback($uploads)); }
        catch (\Throwable $error) {
            foreach ($uploads as $path) Storage::disk('public')->delete($path);
            throw $error;
        }
    }

    public function apply(Connection $db, string $table, string $ownerKey, int $ownerId, array $changes, array $files, \ArrayObject $uploads): void
    {
        if (!$changes && !$files) return;
        $query = $db->table($table)->where($ownerKey, $ownerId);
        $rows = (clone $query)->lockForUpdate()->get()->keyBy('id');
        $ordered = $table === 'hotel_room_type_image';
        $delete = array_map('intval', $changes['delete_ids'] ?? []);
        foreach ($delete as $id) {
            if (!$rows->has($id)) throw ValidationException::withMessages(['image_changes.delete_ids' => 'Ảnh không thuộc đối tượng đang sửa. Hãy tải lại form.']);
        }
        if (isset($changes['primary_id']) && (!$rows->has($changes['primary_id']) || in_array((int) $changes['primary_id'], $delete, true))) {
            throw ValidationException::withMessages(['image_changes.primary_id' => 'Ảnh đại diện phải thuộc đối tượng và không bị đánh dấu xóa.']);
        }
        if (isset($changes['order'])) {
            $expected = $rows->keys()->reject(fn($id) => in_array((int)$id, $delete, true))->map(fn($id) => 's-' . $id)->all();
            foreach (array_keys($files) as $index) $expected[] = 'n-' . $index;
            $actual = $changes['order']; sort($expected); sort($actual);
            if (!$ordered || $expected !== $actual) throw ValidationException::withMessages(['image_changes.order' => 'Thứ tự ảnh phải chứa đầy đủ ảnh của loại phòng đang sửa, không trùng hoặc có ảnh của đối tượng khác.']);
        }
        if ($delete) {
            if ($ordered) foreach ($delete as $id) {
                $path = preg_replace('~^storage/~', '', $rows[$id]->image_url);
                if (str_starts_with($path, 'accommodation/hotel_room_type_image/' . $ownerId . '/')) $db->table('roomtype_asset_cleanup')->updateOrInsert(['path'=>$path], []);
            }
            (clone $query)->whereIn('id', $delete)->delete();
        }
        $newIds = [];
        $position = $ordered ? (int)($rows->max('sort_order') ?? -1) + 1 : 0;
        foreach ($files as $file) {
            $directory = 'accommodation/' . $table . '/' . $ownerId;
            $path = $directory . '/' . $file->hashName();
            $uploads[] = $path;
            if (!$file->storeAs($directory, basename($path), 'public')) throw new \RuntimeException('Không thể lưu file ảnh. Vui lòng thử lại.');
            $newIds[] = $db->table($table)->insertGetId([$ownerKey => $ownerId, 'image_url' => 'storage/' . $path, 'is_primary' => false,
                ...($ordered ? ['sort_order'=>$position++] : [])]);
        }
        if (isset($changes['order'])) foreach ($changes['order'] as $position => $key) {
            $id = str_starts_with($key,'s-') ? (int)substr($key,2) : $newIds[(int)substr($key,2)];
            (clone $query)->where('id',$id)->update(['sort_order'=>$position]);
        }
        $primary = isset($changes['primary_new']) ? $newIds[$changes['primary_new']] : ($changes['primary_id'] ?? null);
        // If the current cover was removed, the first retained image becomes the cover.
        if ($primary === null && ($delete || $files) && !(clone $query)->where('is_primary', true)->exists()) {
            $fallback = clone $query;
            if ($ordered) $fallback->orderBy('sort_order');
            $primary = $fallback->orderBy('id')->value('id');
        }
        if ($primary !== null) {
            (clone $query)->update(['is_primary' => false]);
            (clone $query)->where('id', $primary)->update(['is_primary' => true]);
        }
    }

    public function gallery(Connection $db, string $table, string $ownerKey, int $ownerId): array
    {
        $query = $db->table($table)->where($ownerKey, $ownerId);
        if ($table === 'hotel_room_type_image') $query->orderBy('sort_order');
        else $query->orderByDesc('is_primary');
        return $query->orderBy('id')->get()
            ->map(fn($row) => [...(array) $row, 'display_url' => AccommodationImage::localUrl($row->image_url) ?? AccommodationImage::placeholder()])->all();
    }

    /** Durable retry list; only remove our uniquely named assets after the DB transaction commits. */
    public function cleanup(): int
    {
        if (config('roomtype-v2.production_manifest') !== null || config('roomtype-v2.prepared_routes',false)) return 0;
        $db = \Illuminate\Support\Facades\DB::connection();
        if (!$db->getSchemaBuilder()->hasTable('roomtype_asset_cleanup') || $db->transactionLevel() !== 0) return 0;
        $removed = 0;
        foreach ($db->table('roomtype_asset_cleanup')->limit(100)->get() as $item) {
            if (!preg_match('~^accommodation/hotel_room_type_image/[1-9]\d*/[a-zA-Z0-9]+\.(jpg|jpeg|png|webp)$~D', $item->path)) continue;
            $linked = false;
            foreach (['hotel_room_type_image','room_type_image','room_image','hotel_image'] as $table) {
                if (!$db->getSchemaBuilder()->hasTable($table)) continue;
                // Existing links may store an absolute URL, public/ prefix or URL-encoded path.
                foreach ($db->table($table)->pluck('image_url') as $reference) {
                    $path = rawurldecode(str_replace('\\', '/', parse_url($reference, PHP_URL_PATH) ?: ''));
                    if (str_ends_with('/' . ltrim($path, '/'), '/storage/' . $item->path)
                        || str_ends_with('/' . ltrim($path, '/'), '/storage/app/public/' . $item->path)) {
                        $linked = true;
                        break 2;
                    }
                }
            }
            if ($linked) continue;
            try {
                if (Storage::disk('public')->delete($item->path)) { $db->table('roomtype_asset_cleanup')->where('id',$item->id)->delete(); $removed++; }
            } catch (\Throwable $error) { \Illuminate\Support\Facades\Log::warning('RoomType asset cleanup queued for retry.'); }
        }
        return $removed;
    }
}
