<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class ExperienceSchema
{
    public function ready(): bool
    {
        return Schema::hasColumn('location', 'code')
            && Schema::hasColumns('attraction', ['slug', 'experience_category', 'experience_summary', 'experience_duration_minutes',
                'experience_source_url', 'experience_location_code', 'experience_verified_by',
                'experience_verified_at', 'experience_published_at', 'experience_archived_at'])
            && Schema::hasTable('attraction_image');
    }

    public function ensureReady(): void
    {
        abort_unless($this->ready(), 503, 'Trải nghiệm đang được cập nhật. Vui lòng quay lại sau.');
    }
}
