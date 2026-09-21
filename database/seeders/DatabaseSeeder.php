<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 1. LOCATIONS
        DB::table('location')->insert([
            ['name' => 'Hà Nội',      'region' => 'Miền Bắc',  'country' => 'Việt Nam', 'latitude' => 21.0285,  'longitude' => 105.8542, 'created_at' => $now],
            ['name' => 'Hồ Chí Minh', 'region' => 'Miền Nam',  'country' => 'Việt Nam', 'latitude' => 10.8231,  'longitude' => 106.6297, 'created_at' => $now],
            ['name' => 'Đà Nẵng',     'region' => 'Miền Trung','country' => 'Việt Nam', 'latitude' => 16.0544,  'longitude' => 108.2022, 'created_at' => $now],
            ['name' => 'Phú Quốc',    'region' => 'Miền Nam',  'country' => 'Việt Nam', 'latitude' => 10.2899,  'longitude' => 103.9840, 'created_at' => $now],
            ['name' => 'Nha Trang',   'region' => 'Miền Trung','country' => 'Việt Nam', 'latitude' => 12.2388,  'longitude' => 109.1967, 'created_at' => $now],
            ['name' => 'Hội An',      'region' => 'Miền Trung','country' => 'Việt Nam', 'latitude' => 15.8801,  'longitude' => 108.3380, 'created_at' => $now],
            ['name' => 'Đà Lạt',      'region' => 'Miền Nam',  'country' => 'Việt Nam', 'latitude' => 11.9404,  'longitude' => 108.4583, 'created_at' => $now],
            ['name' => 'Huế',         'region' => 'Miền Trung','country' => 'Việt Nam', 'latitude' => 16.4637,  'longitude' => 107.5909, 'created_at' => $now],
        ]);

        // 2. ROOM TYPES
        DB::table('room_type')->insert([
            ['name' => 'Standard',     'capacity' => 2, 'base_price' => 500000,  'bed_type' => 'Double', 'description' => 'Phòng tiêu chuẩn thoải mái với đầy đủ tiện nghi cơ bản.', 'created_at' => $now],
            ['name' => 'Superior',     'capacity' => 2, 'base_price' => 800000,  'bed_type' => 'Double', 'description' => 'Phòng superior rộng rãi với view đẹp và nội thất hiện đại.', 'created_at' => $now],
            ['name' => 'Deluxe',       'capacity' => 2, 'base_price' => 1200000, 'bed_type' => 'King',   'description' => 'Phòng deluxe sang trọng với ban công và view thành phố.', 'created_at' => $now],
            ['name' => 'Suite',        'capacity' => 3, 'base_price' => 2500000, 'bed_type' => 'King',   'description' => 'Phòng suite cao cấp với phòng khách riêng biệt và bồn tắm.', 'created_at' => $now],
            ['name' => 'Family',       'capacity' => 4, 'base_price' => 1800000, 'bed_type' => 'Twin',   'description' => 'Phòng gia đình rộng lớn phù hợp cho cả gia đình.', 'created_at' => $now],
            ['name' => 'Presidential', 'capacity' => 4, 'base_price' => 5000000, 'bed_type' => 'King',   'description' => 'Phòng tổng thống đẳng cấp nhất với dịch vụ butler riêng.', 'created_at' => $now],
        ]);

        // 3. HOTELS
        $hotels = [
            // Hà Nội
            ['location_id' => 1, 'name' => 'Sofitel Legend Metropole Hà Nội',      'phone' => '024 3826 6919', 'email' => 'metropole@sofitel.com',         'address' => '15 Ngô Quyền, Hoàn Kiếm, Hà Nội',              'latitude' => 21.0245, 'longitude' => 105.8562, 'star_rating' => 5, 'description' => 'Khách sạn biểu tượng của Hà Nội với lịch sử hơn 100 năm, nằm ngay trung tâm phố cổ.',                    'check_in_time' => '14:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.9],
            ['location_id' => 1, 'name' => 'JW Marriott Hotel Hanoi',               'phone' => '024 3833 5588', 'email' => 'jwmarriott.hanoi@marriott.com',  'address' => '8 Đỗ Đức Dục, Nam Từ Liêm, Hà Nội',            'latitude' => 21.0172, 'longitude' => 105.7817, 'star_rating' => 5, 'description' => 'Khách sạn 5 sao hiện đại với kiến trúc độc đáo hình con rồng bên hồ Tây.',                               'check_in_time' => '15:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.8],
            ['location_id' => 1, 'name' => 'Hanoi La Siesta Hotel & Spa',           'phone' => '024 3926 4333', 'email' => 'info@hanoilasiesta.com',         'address' => '94 Mã Mây, Hoàn Kiếm, Hà Nội',                 'latitude' => 21.0338, 'longitude' => 105.8508, 'star_rating' => 4, 'description' => 'Khách sạn boutique nằm ngay phố cổ Hà Nội, thiết kế ấm cúng với spa đẳng cấp.',                        'check_in_time' => '14:00:00', 'check_out_time' => '11:00:00', 'status' => 'active', 'avg_rating' => 4.6],
            ['location_id' => 1, 'name' => 'Lotte Hotel Hanoi',                     'phone' => '024 3333 1000', 'email' => 'hanoi@lottehotel.com',           'address' => '54 Liễu Giai, Ba Đình, Hà Nội',                 'latitude' => 21.0300, 'longitude' => 105.8167, 'star_rating' => 5, 'description' => 'Tháp đôi biểu tượng với tầng quan sát 65 tầng, trung tâm mua sắm và ẩm thực cao cấp.',                  'check_in_time' => '15:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.7],

            // Hồ Chí Minh
            ['location_id' => 2, 'name' => 'Park Hyatt Saigon',                    'phone' => '028 3824 1234', 'email' => 'saigon.park@hyatt.com',          'address' => '2 Công Trường Lam Sơn, Quận 1, TP.HCM',         'latitude' => 10.7769, 'longitude' => 106.7009, 'star_rating' => 5, 'description' => 'Khách sạn sang trọng bậc nhất Sài Gòn tọa lạc ngay trung tâm quận 1.',                                  'check_in_time' => '15:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.9],
            ['location_id' => 2, 'name' => 'Caravelle Saigon',                     'phone' => '028 3823 4999', 'email' => 'reservation@caravellehotel.com', 'address' => '19-23 Công Trường Lam Sơn, Quận 1, TP.HCM',    'latitude' => 10.7763, 'longitude' => 106.7021, 'star_rating' => 5, 'description' => 'Khách sạn lịch sử với view panorama tuyệt đẹp nhìn ra trung tâm Sài Gòn.',                              'check_in_time' => '14:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.7],
            ['location_id' => 2, 'name' => 'Liberty Central Saigon Citypoint',     'phone' => '028 3823 6666', 'email' => 'citypoint@libertyhotels.com.vn', 'address' => '59-61 Pasteur, Quận 1, TP.HCM',                'latitude' => 10.7745, 'longitude' => 106.7003, 'star_rating' => 4, 'description' => 'Khách sạn 4 sao hiện đại với hồ bơi rooftop và view thành phố tuyệt đẹp.',                               'check_in_time' => '14:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.5],
            ['location_id' => 2, 'name' => 'The Reverie Saigon',                   'phone' => '028 3823 6688', 'email' => 'info@thereveriesaigon.com',      'address' => '22-36 Nguyễn Huệ, Quận 1, TP.HCM',             'latitude' => 10.7736, 'longitude' => 106.7027, 'star_rating' => 5, 'description' => 'Khách sạn ultra-luxury với phong cách Ý cổ điển ngay phố đi bộ Nguyễn Huệ.',                           'check_in_time' => '15:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.9],

            // Đà Nẵng
            ['location_id' => 3, 'name' => 'InterContinental Danang Sun Peninsula', 'phone' => '0236 3938 888', 'email' => 'danang@ihg.com',                'address' => 'Bán đảo Sơn Trà, Đà Nẵng',                     'latitude' => 16.1167, 'longitude' => 108.2833, 'star_rating' => 5, 'description' => 'Resort 5 sao đẳng cấp thế giới nằm trên bán đảo Sơn Trà với view biển tuyệt vời.',                    'check_in_time' => '15:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.9],
            ['location_id' => 3, 'name' => 'Pullman Danang Beach Resort',           'phone' => '0236 3958 888', 'email' => 'pullman.danang@accor.com',       'address' => '101 Võ Nguyên Giáp, Đà Nẵng',                  'latitude' => 16.0544, 'longitude' => 108.2473, 'star_rating' => 5, 'description' => 'Resort bãi biển sang trọng với bãi biển riêng và hệ thống hồ bơi đẳng cấp.',                           'check_in_time' => '15:00:00', 'check_out_time' => '11:00:00', 'status' => 'active', 'avg_rating' => 4.7],
            ['location_id' => 3, 'name' => 'Brilliant Hotel Danang',               'phone' => '0236 3222 999', 'email' => 'info@brillianthotel.vn',         'address' => '162 Bạch Đằng, Hải Châu, Đà Nẵng',             'latitude' => 16.0678, 'longitude' => 108.2243, 'star_rating' => 4, 'description' => 'Khách sạn 4 sao nằm bên sông Hàn với tầm nhìn ra cầu Rồng nổi tiếng.',                                'check_in_time' => '14:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.5],

            // Phú Quốc
            ['location_id' => 4, 'name' => 'JW Marriott Phu Quoc Emerald Bay',     'phone' => '0297 3779 999', 'email' => 'jwphuquoc@marriott.com',         'address' => 'Bãi Khem, An Thới, Phú Quốc',                   'latitude' => 10.0339, 'longitude' => 104.0167, 'star_rating' => 5, 'description' => 'Resort sang trọng bậc nhất Phú Quốc với kiến trúc Đông Dương độc đáo.',                                'check_in_time' => '15:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.9],
            ['location_id' => 4, 'name' => 'Vinpearl Resort & Spa Phú Quốc',       'phone' => '0297 3598 888', 'email' => 'phuquoc@vinpearl.com',           'address' => 'Bãi Dài, Gành Dầu, Phú Quốc',                  'latitude' => 10.3833, 'longitude' => 103.8667, 'star_rating' => 5, 'description' => 'Khu nghỉ dưỡng đẳng cấp với bãi biển riêng dài nhất Phú Quốc.',                                          'check_in_time' => '14:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.8],

            // Nha Trang
            ['location_id' => 5, 'name' => 'Sheraton Nha Trang Hotel & Spa',       'phone' => '0258 3880 000', 'email' => 'nhatrang@sheraton.com',          'address' => '26-28 Trần Phú, Nha Trang',                     'latitude' => 12.2388, 'longitude' => 109.1967, 'star_rating' => 5, 'description' => 'Khách sạn 5 sao nằm ngay bãi biển Nha Trang với view biển panorama tuyệt vời.',                       'check_in_time' => '15:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.7],
            ['location_id' => 5, 'name' => 'Sunrise Nha Trang Beach Hotel',        'phone' => '0258 3820 999', 'email' => 'info@sunrisenhatrang.com.vn',    'address' => '12-14 Trần Phú, Nha Trang',                     'latitude' => 12.2402, 'longitude' => 109.1963, 'star_rating' => 4, 'description' => 'Khách sạn 4 sao cổ điển với vị trí đắc địa ngay bãi biển Nha Trang.',                                 'check_in_time' => '14:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.5],

            // Hội An
            ['location_id' => 6, 'name' => 'Four Seasons Resort The Nam Hai',      'phone' => '0235 3940 000', 'email' => 'namhai@fourseasons.com',         'address' => 'Đường Lạc Long Quân, Điện Bàn, Quảng Nam',      'latitude' => 15.8669, 'longitude' => 108.3248, 'star_rating' => 5, 'description' => 'Resort 5 sao đẳng cấp thế giới với villa riêng và 3 hồ bơi vô cực.',                                  'check_in_time' => '15:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.9],
            ['location_id' => 6, 'name' => 'Anantara Hoi An Resort',               'phone' => '0235 3914 555', 'email' => 'hoian@anantara.com',             'address' => '1 Phạm Hồng Thái, Hội An',                      'latitude' => 15.8773, 'longitude' => 108.3271, 'star_rating' => 5, 'description' => 'Resort boutique bên sông Thu Bồn với kiến trúc truyền thống Việt Nam.',                               'check_in_time' => '14:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.8],

            // Đà Lạt
            ['location_id' => 7, 'name' => 'Dalat Palace Heritage Hotel',          'phone' => '0263 3825 444', 'email' => 'palace@dalatpalace.vn',          'address' => '2 Trần Phú, Đà Lạt',                            'latitude' => 11.9422, 'longitude' => 108.4403, 'star_rating' => 5, 'description' => 'Khách sạn cổ điển Pháp được xây dựng từ năm 1922, biểu tượng của Đà Lạt.',                             'check_in_time' => '14:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.7],
            ['location_id' => 7, 'name' => 'Ana Mandara Villas Dalat Resort',      'phone' => '0263 3555 888', 'email' => 'anamandara.dalat@sixsenses.com', 'address' => 'Lê Lai, Đà Lạt',                                'latitude' => 11.9341, 'longitude' => 108.4433, 'star_rating' => 5, 'description' => 'Resort villa kiểu Pháp giữa rừng thông Đà Lạt với không khí lãng mạn.',                                'check_in_time' => '15:00:00', 'check_out_time' => '11:00:00', 'status' => 'active', 'avg_rating' => 4.8],

            // Huế
            ['location_id' => 8, 'name' => 'Azerai La Residence Hue',              'phone' => '0234 3837 475', 'email' => 'laresidence@azerai.com',         'address' => '5 Lê Lợi, Phú Hội, Huế',                       'latitude' => 16.4666, 'longitude' => 107.5936, 'star_rating' => 5, 'description' => 'Khách sạn di sản Art Deco bên bờ sông Hương, biểu tượng lãng mạn của Cố đô Huế.',                    'check_in_time' => '14:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.8],
            ['location_id' => 8, 'name' => 'Pilgrimage Village Boutique Resort',   'phone' => '0234 3885 461', 'email' => 'info@pilgrimagevillage.com',     'address' => '130 Minh Mạng, Thủy Xuân, Huế',                 'latitude' => 16.4480, 'longitude' => 107.5672, 'star_rating' => 4, 'description' => 'Resort boutique giữa làng quê xanh mát, cách trung tâm Huế 5 phút.',                                  'check_in_time' => '14:00:00', 'check_out_time' => '12:00:00', 'status' => 'active', 'avg_rating' => 4.6],
        ];

        foreach ($hotels as &$h) {
            $h['google_place_id'] = null;
            $h['created_at']      = $now;
        }
        DB::table('hotel')->insert($hotels);

        // 4. AMENITIES
        $allAmenities  = ['wifi', 'pool', 'gym', 'spa', 'parking', 'restaurant', 'bar', 'breakfast', 'room_service', 'airport_shuttle'];
        $basicAmenities = ['wifi', 'parking', 'restaurant', 'breakfast', 'room_service', 'bar'];

        $hotelIds = DB::table('hotel')->pluck('id', 'star_rating')->toArray();
        $allHotels = DB::table('hotel')->get();
        foreach ($allHotels as $hotel) {
            $list = $hotel->star_rating >= 5 ? $allAmenities : $basicAmenities;
            foreach ($list as $amenity) {
                DB::table('hotel_amenity')->insert([
                    'hotel_id' => $hotel->id,
                    'amenity'  => $amenity,
                    'icon'     => $amenity . '.svg',
                ]);
            }
        }

        // 5. ROOMS
        $roomConfigs = [
            ['room_type_id' => 1, 'qty' => 5, 'price' => 500000,  'capacity' => 2, 'floor' => 1],
            ['room_type_id' => 2, 'qty' => 4, 'price' => 800000,  'capacity' => 2, 'floor' => 2],
            ['room_type_id' => 3, 'qty' => 3, 'price' => 1200000, 'capacity' => 2, 'floor' => 3],
            ['room_type_id' => 4, 'qty' => 2, 'price' => 2500000, 'capacity' => 3, 'floor' => 4],
            ['room_type_id' => 5, 'qty' => 2, 'price' => 1800000, 'capacity' => 4, 'floor' => 5],
        ];

        foreach ($allHotels as $hotel) {
            foreach ($roomConfigs as $cfg) {
                for ($i = 1; $i <= $cfg['qty']; $i++) {
                    DB::table('room')->insert([
                        'hotel_id'     => $hotel->id,
                        'room_type_id' => $cfg['room_type_id'],
                        'room_number'  => $cfg['floor'] . str_pad($i, 2, '0', STR_PAD_LEFT),
                        'floor'        => $cfg['floor'],
                        'price'        => $cfg['price'],
                        'capacity'     => $cfg['capacity'],
                        'status'       => 'available',
                        'created_at'   => $now,
                    ]);
                }
            }
        }

        $this->command->info('✅ Done! Đã seed: 8 locations, 6 room types, 20 hotels, amenities, rooms.');

        $this->call([
            AttractionSeeder::class,
        ]);
    }

    
}