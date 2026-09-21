<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Xóa itinerary_items trước vì nó FK trỏ itineraries
        Schema::dropIfExists('itinerary_items');
        Schema::dropIfExists('itineraries');
    }

    public function down(): void
    {
        // Không cần rollback vì đây là bảng rác
    }
};
