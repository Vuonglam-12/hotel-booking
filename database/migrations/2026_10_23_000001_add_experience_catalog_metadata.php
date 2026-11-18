<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Additive only. Existing locations, attractions and AI fields are not rewritten or published.
        Schema::table('location', function (Blueprint $table) {
            $table->string('code', 80)->nullable()->unique();
        });
        Schema::table('attraction', function (Blueprint $table) {
            $table->string('slug', 180)->nullable()->unique();
            $table->string('experience_category', 32)->nullable();
            $table->string('experience_summary', 500)->nullable();
            $table->unsignedSmallInteger('experience_duration_minutes')->nullable();
            $table->string('experience_source_url', 2048)->nullable();
            $table->string('experience_location_code', 80)->nullable();
            $table->integer('experience_verified_by')->nullable();
            $table->timestamp('experience_verified_at')->nullable();
            $table->timestamp('experience_published_at')->nullable();
            $table->timestamp('experience_archived_at')->nullable();
            $table->index(['is_active', 'experience_published_at'], 'attraction_experience_public');
        });
        Schema::create('attraction_image', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attraction_id')->constrained('attraction')->cascadeOnDelete();
            $table->string('image_url', 500);
            $table->boolean('is_primary')->default(false);
            $table->string('caption', 200)->nullable();
            $table->string('source_note', 500)->default('');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attraction_image');
        Schema::table('attraction', function (Blueprint $table) {
            $table->dropIndex('attraction_experience_public');
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'experience_category', 'experience_summary', 'experience_duration_minutes', 'experience_source_url',
                'experience_location_code', 'experience_verified_by', 'experience_verified_at',
                'experience_published_at', 'experience_archived_at']);
        });
        Schema::table('location', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
