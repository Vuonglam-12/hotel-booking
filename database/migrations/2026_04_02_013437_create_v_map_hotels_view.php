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
        DB::statement("CREATE VIEW `v_map_hotels` AS select `h`.`id` AS `id`,`h`.`name` AS `name`,`h`.`address` AS `address`,`h`.`latitude` AS `latitude`,`h`.`longitude` AS `longitude`,`h`.`google_place_id` AS `google_place_id`,`h`.`star_rating` AS `star_rating`,`h`.`avg_rating` AS `avg_rating`,`h`.`check_in_time` AS `check_in_time`,`h`.`check_out_time` AS `check_out_time`,`l`.`name` AS `city`,`l`.`region` AS `region`,min(`r`.`price`) AS `min_price`,(select `i`.`image_url` from `hotel_booking`.`hotel_image` `i` where ((`i`.`hotel_id` = `h`.`id`) and (`i`.`is_primary` = true)) limit 1) AS `primary_image`,(select group_concat(`a`.`amenity` order by `a`.`amenity` ASC separator ',') from `hotel_booking`.`hotel_amenity` `a` where (`a`.`hotel_id` = `h`.`id`)) AS `amenities` from ((`hotel_booking`.`hotel` `h` join `hotel_booking`.`location` `l` on((`h`.`location_id` = `l`.`id`))) left join `hotel_booking`.`room` `r` on(((`r`.`hotel_id` = `h`.`id`) and (`r`.`status` = 'available')))) where ((`h`.`status` = 'active') and (`h`.`latitude` is not null) and (`h`.`longitude` is not null)) group by `h`.`id`");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS `v_map_hotels`");
    }
};
