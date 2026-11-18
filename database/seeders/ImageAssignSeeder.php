<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ImageAssignSeeder extends Seeder
{
    public function run(): void
    {
        $this->assignHotelImages();
        $this->assignRoomImages();
    }

    // Quét thư mục, trả về danh sách path tương đối, sort theo số trong dấu (...)
    private function scanImages(string $relativeDir): array
    {
        $fullPath = public_path($relativeDir);

        if (!File::isDirectory($fullPath)) {
            $this->command?->warn("Không tìm thấy thư mục: {$fullPath}");
            return [];
        }

        return collect(File::files($fullPath))
            ->map(fn ($f) => $f->getFilename())
            ->sort(fn ($a, $b) => $this->extractIndex($a) <=> $this->extractIndex($b))
            ->values()
            ->map(fn ($name) => $relativeDir . '/' . $name)
            ->all();
    }

    // "hotel_ (10).jpg" -> 10 — tránh lỗi sort string ("10" đứng trước "2")
    private function extractIndex(string $filename): int
    {
        preg_match('/\((\d+)\)/', $filename, $m);
        return isset($m[1]) ? (int) $m[1] : 0;
    }

    private function assignHotelImages(): void
    {
        $images = $this->scanImages('image/img_hotel');
        if (empty($images)) return;

        HotelImage::truncate();

        Hotel::orderBy('id')->get()->each(function (Hotel $hotel, int $i) use ($images) {
            HotelImage::create([
                'hotel_id'   => $hotel->id,
                'image_url'  => $images[$i % count($images)],
                'is_primary' => true,
                'created_at' => now(),
            ]);
        });

        $this->command?->info(count($images) . ' ảnh hotel → gán cho ' . Hotel::count() . ' khách sạn.');
    }

    private function assignRoomImages(): void
    {
        $images = $this->scanImages('image/img_room');
        if (empty($images)) return;

        RoomImage::truncate();

        // 1 ảnh cố định / room_type — mọi phòng cùng loại (mọi hotel) dùng chung
        $roomTypeImageMap = [];
        RoomType::orderBy('id')->get()->each(function (RoomType $type, int $i) use (&$roomTypeImageMap, $images) {
            $roomTypeImageMap[$type->id] = $images[$i % count($images)];
        });

        Room::orderBy('id')->get()->each(function (Room $room) use ($roomTypeImageMap) {
            RoomImage::create([
                'room_id'    => $room->id,
                'image_url'  => $roomTypeImageMap[$room->room_type_id] ?? null,
                'is_primary' => true,
                'created_at' => now(),
            ]);
        });

        $this->command?->info(count($images) . ' ảnh room → gán theo ' . count($roomTypeImageMap) . ' room_type, cho ' . Room::count() . ' phòng.');
    }
}