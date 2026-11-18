<?php

namespace App\Support;

use App\Models\HotelAmenity;

final class HotelAmenityCatalog
{
    public static function labels(): array
    {
        return [
            'wifi' => 'WiFi', 'pool' => 'Hồ bơi', 'gym' => 'Phòng gym', 'spa' => 'Spa',
            'restaurant' => 'Nhà hàng', 'bar' => 'Quầy bar', 'parking' => 'Bãi đậu xe',
            'airport_shuttle' => 'Đưa đón sân bay', 'breakfast' => 'Bữa sáng', 'room_service' => 'Room service',
        ];
    }

    public static function normalize(string $key): string
    {
        $key = trim(preg_replace('/\s+/u', ' ', $key));
        $lower = mb_strtolower($key, 'UTF-8');
        if ($lower === 'airport') return 'airport_shuttle';
        foreach (self::labels() as $value => $label) {
            if ($lower === $value || $lower === mb_strtolower($label, 'UTF-8')) return $value;
        }
        return $key;
    }

    public static function identity(string $name): string
    {
        return mb_strtolower(self::normalize($name), 'UTF-8');
    }

    public static function options(): array
    {
        return collect(self::labels())->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }

    public static function remember(string $name, ?string $icon = null): HotelAmenity
    {
        $name = self::normalize($name);
        // A unique database key also protects simultaneous requests from different Admins.
        return HotelAmenity::query()->firstOrCreate(
            ['catalog_identity' => hash('sha256', self::identity($name))],
            ['hotel_id' => null, 'amenity' => $name, 'icon' => $icon]
        );
    }

    public static function option(HotelAmenity $amenity): array
    {
        return ['id' => $amenity->id, 'key' => $amenity->amenity,
            'label' => self::labels()[$amenity->amenity] ?? $amenity->amenity];
    }

    public static function storedOptions(): array
    {
        return HotelAmenity::catalog()->orderBy('amenity')->get()
            ->map(fn($amenity) => self::option($amenity))->all();
    }
}
