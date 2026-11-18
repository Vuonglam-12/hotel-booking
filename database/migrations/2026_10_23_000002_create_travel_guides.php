<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_category', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 140)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('guide_post', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('slug', 220)->unique();
            $table->string('title', 240);
            $table->text('excerpt')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('cover_path', 500)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('guide_category')->nullOnDelete();
            // Existing location.id is a signed integer, not a foreignId/bigint.
            $table->integer('location_id')->nullable();
            $table->foreign('location_id')->references('id')->on('location')->nullOnDelete();
            $table->string('author_name', 120)->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            // Private, immutable import evidence. Never serialized through public APIs.
            $table->json('legacy_metadata')->nullable();
            $table->longText('legacy_body')->nullable();
            $table->string('legacy_content_sha256', 64)->nullable();
            $table->string('legacy_import_state', 24)->nullable();
            $table->timestamps();
            $table->index(['status', 'archived_at', 'published_at'], 'guide_publication_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_post');
        Schema::dropIfExists('guide_category');
    }
};
