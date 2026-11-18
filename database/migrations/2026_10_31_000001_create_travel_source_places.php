<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('travel_source_place',function(Blueprint $t){
            $t->id();$t->string('source',160);$t->string('external_id',100);$t->integer('location_id');
            $t->foreign('location_id')->references('id')->on('location')->restrictOnDelete();
            $t->string('name');$t->string('category');$t->string('area');$t->text('address')->nullable();$t->string('phone',100)->nullable();
            $t->text('hours_text')->nullable();$t->unsignedInteger('price_min')->nullable();$t->unsignedInteger('price_max')->nullable();$t->text('price_unit')->nullable();
            $t->text('description')->nullable();$t->json('tags');$t->unsignedSmallInteger('visit_minutes')->nullable();
            $t->text('maps_url');$t->text('maps_link_type')->nullable();$t->text('status_text')->nullable();$t->boolean('is_enabled')->default(false);
            foreach(['operation_evidence','source_url','secondary_sources','data_notes','licensed_image_url'] as $field)$t->text($field)->nullable();
            $t->date('evidence_date')->nullable();$t->date('source_checked_at')->nullable();$t->decimal('latitude',10,7)->nullable();$t->decimal('longitude',10,7)->nullable();
            $t->char('content_hash',64);$t->char('guide_hash',64);$t->timestamps();
            $t->unique(['source','external_id'],'travel_source_place_identity');$t->index(['location_id','is_enabled']);
        });
    }
    public function down(): void { Schema::dropIfExists('travel_source_place'); }
};
