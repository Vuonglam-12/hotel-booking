<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class GuidePost extends Model
{
    protected $table = 'guide_post';
    protected $guarded = ['id', 'legacy_id', 'legacy_metadata', 'legacy_body', 'legacy_content_sha256', 'legacy_import_state'];
    protected $hidden = ['legacy_metadata', 'legacy_body', 'legacy_content_sha256', 'legacy_import_state'];
    protected $casts = ['is_featured' => 'boolean', 'published_at' => 'datetime', 'archived_at' => 'datetime', 'legacy_metadata' => 'array'];
    public function category() { return $this->belongsTo(GuideCategory::class, 'category_id'); }
    public function location() { return $this->belongsTo(Location::class, 'location_id'); }
}
