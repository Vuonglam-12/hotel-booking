<?php

namespace App\Services\Itinerary;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/** Photos are resolved from owned server records, never client names, URLs or model output. */
final class PlannerPhotos
{
    public function proposal(Customer $customer, array $reference, ?int $locationId = null): array
    {
        $json=DB::table('chat_message as messages')->join('chat_session as sessions','sessions.id','=','messages.session_id')
            ->where('sessions.customer_id',$customer->id)->where('messages.id',$reference['message_id'])->where('messages.role','bot')->value('messages.entities_json');
        $assistant=$json?(json_decode($json,true)['assistant'] ?? null):null;
        abort_unless($assistant && (!$locationId || ($assistant['state']['location_id'] ?? null)===$locationId),422,'Ảnh không thuộc đề xuất của chuyến đi này.');
        $timeline=collect($assistant['components'] ?? [])->firstWhere('type','timeline');
        $day=collect($timeline['days'] ?? [])->firstWhere('day_number',$reference['day_number']);
        $source=$day['items'][$reference['item_index']] ?? null;
        abort_unless($source,422,'Không tìm thấy địa điểm trong đề xuất.');
        return $source;
    }

    public function place(array $source): array
    {
        return ['name'=>$source['name'] ?? $source['title'] ?? '', 'coordinates'=>$source['coordinates'] ?? null,
            'address'=>$source['address'] ?? null,'wikidata_id'=>$source['wikidata_id'] ?? null,
            'provider'=>!empty($source['source_place_id'])?'google_sheet':($source['provider'] ?? null),'image'=>$source['image'] ?? null];
    }

    public function matches(array $snapshot, string $title, ?string $placeName): bool
    {
        return isset($snapshot['image_place_name'])
            ? trim($snapshot['image_place_name'])===trim($placeName ?? '') && ($snapshot['image_title'] ?? '')===$title
            : empty($snapshot['place_changed']) && ($snapshot['name'] ?? '')===$title;
    }

    public function eligible(array $place): bool
    {
        return ($place['provider'] ?? '')!=='google_sheet' && !empty($place['name']) && !empty($place['coordinates']);
    }
}
