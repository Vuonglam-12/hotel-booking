<?php

namespace App\Services\Itinerary;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class ConversationState
{
    public function merge(array $previous, array $patch, array $locations): array
    {
        if(($patch['pace'] ?? null)==='normal')$patch['pace']='moderate';
        $rules = ['destination_name' => 'nullable|string|max:150','start_date' => 'nullable|date_format:Y-m-d','end_date' => 'nullable|date_format:Y-m-d',
            'duration_days' => 'nullable|integer|min:1|max:30','adults' => 'nullable|integer|min:1|max:8',
            'duration_range'=>'nullable|array|size:2','duration_range.*'=>'integer|min:1|max:30',
            'children_count' => 'nullable|integer|min:0|max:7','infants_count' => 'nullable|integer|min:0|max:7',
            'children' => 'nullable|array|max:7','infants' => 'nullable|array|max:7',
            'children.*.age' => 'nullable|integer|min:0|max:215','children.*.unit' => 'required|in:years,months',
            'infants.*.age' => 'nullable|integer|min:0|max:23','infants.*.unit' => 'required|in:years,months',
            'children.*.height_cm'=>'nullable|integer|min:40|max:220','infants.*.height_cm'=>'nullable|integer|min:30|max:140',
            'budget_flexible'=>'sometimes|boolean','allow_assumptions'=>'sometimes|boolean',
            'budget' => 'nullable|integer|min:1|max:9999999999','stay_mode' => 'nullable|in:own_accommodation,aurel_hotel,day_trip',
            'transport' => 'nullable|in:walk,drive,bicycle','requested_transport' => 'nullable|string|max:100',
            'arrival_time' => 'nullable|date_format:H:i','departure_time' => 'nullable|date_format:H:i',
            'activity_window_start' => 'nullable|date_format:H:i','activity_window_end' => 'nullable|date_format:H:i',
            'place_focus' => 'nullable|in:general,entertainment,food',
            'pace' => 'nullable|in:relaxed,moderate,packed','interests' => 'nullable|array|max:30','interests.*' => 'string|max:100',
            'excluded_ids'=>'nullable|array|max:500','excluded_ids.*'=>'regex:/^[A-Za-z0-9:_-]{1,100}$/D',
            'constraints' => 'nullable|array|max:30','constraints.*' => 'string|max:150',
            'food_restrictions' => 'nullable|array|max:30','food_restrictions.*' => 'string|max:100'];
        $allowed = array_filter(array_keys($rules), fn($key) => !str_contains($key, '.'));
        if (array_diff(array_keys($patch), $allowed)) app(TravelDialogue::class)->fail('AI chưa hiểu rõ thông tin chuyến đi. Bạn thử diễn đạt lại nhé.', 'invalid_state');
        $validator = Validator::make($patch, $rules);
        if ($validator->fails()) app(TravelDialogue::class)->fail('Một thông tin chuyến đi chưa hợp lệ. Bạn kiểm tra lại ngày, số người hoặc ngân sách nhé.', 'invalid_state', 422);
        $state = array_replace($previous, $validator->validated());
        if(array_key_exists('duration_days',$patch) && !array_key_exists('duration_range',$patch))unset($state['duration_range']);
        if(!empty($patch['budget']))$state['budget_flexible']=false;
        if (!empty($state['requested_transport'])) {
            $requested=$this->normalize($state['requested_transport']);
            $mode=match($requested){'oto','xeoto','xehoi','taxi','drive'=>'drive','dibo','walk'=>'walk','xedap','bicycle'=>'bicycle',default=>null};
            if($mode){$state['transport']=$mode;$state['requested_transport']=null;}
        }
        if(!empty($patch['transport']) && !array_key_exists('requested_transport',$patch))$state['requested_transport']=null;
        foreach (['children','infants'] as $field) {
            if (array_key_exists($field, $patch) && $patch[$field] !== null) $state[$field.'_count'] = count($patch[$field]);
            elseif (array_key_exists($field.'_count', $patch) && ($patch[$field.'_count'] !== ($previous[$field.'_count'] ?? null))) $state[$field] = null;
            foreach ($state[$field] ?? [] as $person) {
                if (($person['age'] ?? null) === null) continue;
                $months = $person['age'] * ($person['unit'] === 'years' ? 12 : 1);
                if (($field === 'children' && ($months < 24 || $months > 215)) || ($field === 'infants' && $months > 23)) {
                    app(TravelDialogue::class)->fail('Tuổi trẻ em hoặc em bé chưa phù hợp. Bạn kiểm tra lại giúp mình nhé.', 'invalid_party_age', 422);
                }
            }
        }
        if (($state['adults'] ?? 1) + ($state['children_count'] ?? 0) + ($state['infants_count'] ?? 0) > 8) {
            app(TravelDialogue::class)->fail('Hiện AUREL hỗ trợ lên lịch cho tối đa 8 người trong một nhóm. Bạn muốn chia nhóm như thế nào?', 'party_limit', 422);
        }
        if (!empty($state['destination_name'])) {
            $match = $this->resolve($state['destination_name'], $locations);
            $state['location_id'] = $match['id'] ?? null;$state['supported'] = $match !== null;
            if ($match) $state['destination_name'] = $match['name'];
        } else {$state['location_id'] = null;$state['supported'] = null;}
        if (array_key_exists('start_date', $patch) && !array_key_exists('end_date', $patch) && !empty($state['start_date']) && !empty($state['duration_days'])) {
            $state['end_date'] = CarbonImmutable::parse($state['start_date'])->addDays($state['duration_days'] - 1)->toDateString();
        } elseif (array_key_exists('duration_days', $patch) && !array_key_exists('end_date', $patch) && !empty($state['start_date'])) {
            $state['end_date'] = CarbonImmutable::parse($state['start_date'])->addDays($state['duration_days'] - 1)->toDateString();
        }
        if (!empty($state['start_date']) && !empty($state['end_date'])) {
            if ($state['end_date'] < $state['start_date'] || $state['start_date'] < now('Asia/Ho_Chi_Minh')->toDateString()) {
                app(TravelDialogue::class)->fail('Ngày đi và ngày về chưa phù hợp. Bạn muốn khởi hành ngày nào?', 'invalid_trip_dates', 422);
            }
            $state['duration_days'] = CarbonImmutable::parse($state['start_date'])->diffInDays(CarbonImmutable::parse($state['end_date'])) + 1;
            if ($state['duration_days'] > 30) app(TravelDialogue::class)->fail('Hiện mình có thể lập một hành trình tối đa 30 ngày. Bạn muốn bắt đầu với chặng nào?', 'trip_duration_limit', 422);
        }
        if (($state['stay_mode'] ?? null) === 'day_trip') {
            $state['duration_days'] = 1;
            if (!empty($state['start_date'])) $state['end_date'] = $state['start_date'];
        }
        return $state;
    }

    public function resolve(string $name, array $locations): ?array
    {
        $term = $this->normalize($name);
        if (in_array($term, ['saigon','sg','tphcm','hcm','thanhphohochiminh','tphochiminh'], true)) $term = 'hochiminh';
        $matches = array_values(array_filter($locations, fn($location) => $this->normalize($location['name']) === $term));
        return count($matches) === 1 ? $matches[0] : null;
    }

    public function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii(preg_replace('/^(?:thành phố|tỉnh|tp\.?)[\s]+/iu', '', trim($value)))));
    }
}
