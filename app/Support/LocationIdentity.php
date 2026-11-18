<?php

namespace App\Support;

use App\Models\Location;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Internal catalog keys, not government administrative codes. Never inferred from auto-increment IDs. */
final class LocationIdentity
{
    public static function assignCode(int $id, string $code): Location
    {
        return DB::transaction(function () use ($id, $code) {
            $location = Location::lockForUpdate()->findOrFail($id);
            if ($location->code && $location->code !== $code) {
                throw \Illuminate\Validation\ValidationException::withMessages(['code' => 'Mã địa phương đã gán được giữ cố định.']);
            }
            $location->update(['code' => $code]);
            return $location;
        });
    }

    public const SEED_NAMES = [
        'vn-ha-noi' => 'Hà Nội', 'vn-ho-chi-minh' => 'Hồ Chí Minh',
        'vn-da-lat' => 'Đà Lạt', 'vn-phu-quoc' => 'Phú Quốc',
    ];

    public static function resolveSeedKey(string $key): Location
    {
        if (!isset(self::SEED_NAMES[$key])) throw new \RuntimeException('Unknown location seed key: '.$key);
        if (Schema::hasColumn('location', 'code')) {
            $matches = Location::where('code', $key)->get();
            if ($matches->count() === 1) return $matches->first();
            if ($matches->count() > 1) throw new \RuntimeException('Ambiguous location code: '.$key);
        }
        // Compatibility for isolated legacy fixtures. Resolve exactly one canonical name/country; do not assign or change codes.
        $normalize = static fn ($value) => Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($value))));
        $expected = $normalize(self::SEED_NAMES[$key]);
        $matches = Location::all()->filter(static fn ($location) => $normalize($location->name) === $expected
            && in_array($normalize($location->country ?? ''), ['viet nam', 'vietnam'], true));
        if ($matches->count() !== 1) throw new \RuntimeException('Missing or ambiguous location for seed key: '.$key);
        return $matches->first();
    }
}
