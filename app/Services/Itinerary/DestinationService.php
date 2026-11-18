<?php

namespace App\Services\Itinerary;

use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DestinationService
{
    public function supported(int $id): bool
    {
        return Location::whereKey($id)->whereExists(fn ($q) => $q->selectRaw('1')->from('itinerary_destination')
            ->whereColumn('itinerary_destination.location_id', 'location.id')->where('enabled', true))->exists();
    }

    public function listing(?string $search): array
    {
        $term = $this->normalize($search ?? '');
        if (in_array(str_replace(' ', '', $term), ['saigon'], true)) {
            $term = 'ho chi minh';
        }

        return Location::whereExists(fn ($q) => $q->selectRaw('1')->from('itinerary_destination')
            ->whereColumn('itinerary_destination.location_id', 'location.id')->where('enabled', true))
            ->orderBy('name')->get(['id', 'name'])->filter(fn ($location) => $term === '' || str_contains($this->normalize($location->name), $term))
            ->map(fn ($location) => ['id' => $location->id, 'name' => $location->name,
                'provider_region_verified' => $this->verifiedRegion($location->id)])->values()->all();
    }

    public function verifiedRegion(int $id): bool
    {
        $mapping = DB::table('itinerary_region_mapping')->where('location_id', $id)->where('provider', 'geoapify')
            ->where('verification_status', 'verified')->whereNotNull('verified_at')->whereNotNull('provider_region_id')
            ->whereNotNull('verification_evidence')->first();
        if (! $mapping || trim($mapping->provider_region_id) === '') {
            return false;
        }
        $evidence = json_decode($mapping->verification_evidence, true);

        // A radius/nearby result is never acceptable region evidence.
        return is_array($evidence) && in_array($evidence['method'] ?? null, ['administrative_region', 'administrative_boundary'], true)
            && ! empty($evidence['source_reference']);
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($value))));
    }
}
