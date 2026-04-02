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
        DB::statement("CREATE VIEW `v_booking_history` AS select `b`.`id` AS `booking_id`,`b`.`created_at` AS `created_at`,`b`.`check_in` AS `check_in`,`b`.`check_out` AS `check_out`,`b`.`total_price` AS `total_price`,`b`.`status` AS `status`,`b`.`special_request` AS `special_request`,`c`.`name` AS `customer_name`,`c`.`email` AS `email`,`h`.`name` AS `hotel_name`,`h`.`star_rating` AS `star_rating`,`p`.`payment_status` AS `payment_status`,`p`.`payment_method` AS `payment_method`,`p`.`paid_at` AS `paid_at`,(case when (`b`.`chat_session_id` is not null) then 'chatbot' else 'direct' end) AS `booking_source` from (((`hotel_booking`.`booking` `b` join `hotel_booking`.`customer` `c` on((`b`.`customer_id` = `c`.`id`))) join `hotel_booking`.`hotel` `h` on((`b`.`hotel_id` = `h`.`id`))) left join `hotel_booking`.`payment` `p` on((`p`.`booking_id` = `b`.`id`)))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS `v_booking_history`");
    }
};
