<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class GuideCategory extends Model
{
    protected $table = 'guide_category';
    protected $fillable = ['name', 'slug', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function posts() { return $this->hasMany(GuidePost::class, 'category_id'); }
}
