<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function dropFKSafe(string $table, string $fk): void
    {
        $exists = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$table, $fk]);

        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
        }
    }

    private function dropColumnSafe(string $table, string $col): void
    {
        if (Schema::hasColumn($table, $col)) {
            Schema::table($table, fn(Blueprint $t) => $t->dropColumn($col));
        }
    }

    public function up(): void
    {
        // BƯỚC 1: XÓA VIEW
        DB::statement('DROP VIEW IF EXISTS v_map_hotels');
        DB::statement('DROP VIEW IF EXISTS v_map_destinations');
        DB::statement('DROP VIEW IF EXISTS v_map_route');
        DB::statement('DROP VIEW IF EXISTS v_booking_history');

        // BƯỚC 2: chat_message — xóa FK rồi xóa cột
        $this->dropFKSafe('chat_message', 'fk_chat_msg_dest');
        $this->dropColumnSafe('chat_message', 'referenced_destination_id');

        // BƯỚC 3: XÓA BẢNG RÁC
        Schema::dropIfExists('map_search_log');
        Schema::dropIfExists('booking_service');
        Schema::dropIfExists('itinerary_day');
        Schema::dropIfExists('review_image');  // phụ thuộc review
        // destination bị FK từ nhiều bảng — drop FK trước
        $this->dropFKSafe('itinerary_item', 'fk_item_dest');
        $this->dropFKSafe('review', 'fk_review_destination');
        Schema::dropIfExists('destination');
        Schema::dropIfExists('service');
        Schema::dropIfExists('notifications');

        // BƯỚC 4: hotel — giữ lại tọa độ vì app hiện tại đang cần cho tìm kiếm/hiển thị map
        // không xóa google_place_id ở đây vì đa số flow vẫn đang dùng nó

        // BƯỚC 5: location — giữ lại tọa độ vì seeders và UI dùng

        // BƯỚC 6: itinerary_item — xóa tọa độ + destination_id
        $this->dropColumnSafe('itinerary_item', 'latitude');
        $this->dropColumnSafe('itinerary_item', 'longitude');
        $this->dropColumnSafe('itinerary_item', 'destination_id');

        // BƯỚC 7: review — xóa destination_id
        $this->dropColumnSafe('review', 'destination_id');
    }

    public function down(): void
    {
        Schema::table('hotel', function (Blueprint $t) {
            $t->decimal('latitude', 10, 7)->default(0);
            $t->decimal('longitude', 10, 7)->default(0);
            $t->string('google_place_id', 100)->nullable();
        });
        Schema::table('location', function (Blueprint $t) {
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
        });
        Schema::table('itinerary_item', function (Blueprint $t) {
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->integer('destination_id')->nullable();
        });
        Schema::table('review', function (Blueprint $t) {
            $t->integer('destination_id')->nullable();
        });
    }
};