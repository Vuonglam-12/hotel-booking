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
        DB::statement("CREATE VIEW `v_map_route` AS select `ii`.`itinerary_id` AS `itinerary_id`,`ii`.`day_number` AS `day_number`,`ii`.`order_in_day` AS `order_in_day`,`ii`.`title` AS `title`,`ii`.`item_type` AS `item_type`,`ii`.`start_time` AS `start_time`,`ii`.`end_time` AS `end_time`,`ii`.`estimated_cost` AS `estimated_cost`,`ii`.`latitude` AS `latitude`,`ii`.`longitude` AS `longitude`,`h`.`name` AS `hotel_name`,`h`.`google_place_id` AS `hotel_place_id`,`d`.`name` AS `dest_name`,`d`.`google_place_id` AS `dest_place_id` from ((`hotel_booking`.`itinerary_item` `ii` left join `hotel_booking`.`hotel` `h` on((`ii`.`hotel_id` = `h`.`id`))) left join `hotel_booking`.`destination` `d` on((`ii`.`destination_id` = `d`.`id`))) where ((`ii`.`latitude` is not null) and (`ii`.`longitude` is not null)) order by `ii`.`itinerary_id`,`ii`.`day_number`,`ii`.`order_in_day`");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS `v_map_route`");
    }
};
