<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("CREATE VIEW `v_map_destinations` AS select `d`.`id` AS `id`,`d`.`name` AS `name`,`d`.`category` AS `category`,`d`.`description` AS `description`,`d`.`latitude` AS `latitude`,`d`.`longitude` AS `longitude`,`d`.`google_place_id` AS `google_place_id`,`d`.`avg_rating` AS `avg_rating`,`d`.`image_url` AS `image_url`,`l`.`name` AS `city` from (`hotel_booking`.`destination` `d` join `hotel_booking`.`location` `l` on((`d`.`location_id` = `l`.`id`))) where ((`d`.`latitude` is not null) and (`d`.`longitude` is not null))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS `v_map_destinations`");
    }
};
