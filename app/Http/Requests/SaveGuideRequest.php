<?php

namespace App\Http\Requests;

use App\Services\AdminAccommodationImageService;
use Illuminate\Foundation\Http\FormRequest;

final class SaveGuideRequest extends FormRequest
{
    public function authorize(): bool { return $this->user('admin')?->role === 'admin'; }
    public function rules(): array
    {
        // Check setup before exists rules issue queries against missing catalog tables.
        app(\App\Support\GuideSchema::class)->ensureReady();
        return [
            'title' => 'required|string|max:240', 'excerpt' => 'nullable|string|max:600',
            'body_html' => 'nullable|string|max:200000', 'category_id' => 'nullable|integer|exists:guide_category,id',
            'location_id' => 'nullable|integer|exists:location,id', 'author_name' => 'nullable|string|max:120',
            'status' => 'required|in:draft,published', 'is_featured' => 'sometimes|boolean',
            'published_at' => 'nullable|date', 'remove_cover' => 'sometimes|boolean',
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.AdminAccommodationImageService::MAX_KB,
                function ($attribute, $file, $fail) { if (@getimagesize($file->getRealPath()) === false) $fail('Ảnh bìa không hợp lệ.'); }],
        ];
    }
}
