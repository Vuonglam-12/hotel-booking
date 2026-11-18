<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class GuideSchema
{
    public function ready(): bool
    {
        return Schema::hasColumns('guide_post', ['id', 'slug', 'legacy_id', 'title', 'excerpt', 'body_html', 'cover_path',
            'category_id', 'location_id', 'author_name', 'status', 'is_featured', 'published_at', 'archived_at',
            'legacy_metadata', 'legacy_body', 'legacy_content_sha256', 'legacy_import_state', 'created_at', 'updated_at'])
            && Schema::hasColumns('guide_category', ['id', 'name', 'slug', 'is_active', 'created_at', 'updated_at']);
    }

    public function ensureReady(): void
    {
        if (!$this->ready()) abort(response()->json([
            'code' => 'GUIDES_NOT_READY',
            'message' => 'Cẩm nang đang được chuẩn bị. Vui lòng quay lại sau.',
        ], 503)->header('Cache-Control', 'no-store'));
    }
}
