<?php

namespace App\Http\Requests\Itinerary;

use App\Rules\ItineraryInteger;
use App\Services\Itinerary\DestinationService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class PlannerRequest extends MutationRequest
{
    public static function tripRules(): array
    {
        return [
            'location_id' => ['bail', 'required', new ItineraryInteger, 'integer', 'min:1', function ($attribute, $value, $fail) {
                if (! app(DestinationService::class)->supported($value)) $fail('Điểm đến chưa được hỗ trợ.');
            }],
            'duration_days' => ['required', new ItineraryInteger, 'integer', 'min:1', 'max:30'],
            'people' => ['required', new ItineraryInteger, 'integer', 'min:1', 'max:8'],
            'estimated_budget' => ['present', 'nullable', new ItineraryInteger, 'integer', 'min:1', 'max:9999999999'],
            'stay_mode' => ['required', Rule::in(['aurel_hotel', 'own_accommodation', 'day_trip'])],
            'visit_start_date' => ['sometimes','nullable','date_format:Y-m-d'],
            'pace'=>['sometimes',Rule::in(['relaxed','normal','moderate','packed'])],
            'interests'=>['sometimes','array','max:30'],'interests.*'=>['string','max:100'],
            'food_restrictions'=>['sometimes','array','max:30'],'food_restrictions.*'=>['string','max:100'],
            'excluded_ids'=>['sometimes','array','max:500'],'excluded_ids.*'=>['regex:/^[A-Za-z0-9:_-]{1,100}$/D'],
            'planning_reference'=>['sometimes','nullable','array:message_id'],
            'planning_reference.message_id'=>['required_with:planning_reference',new ItineraryInteger,'integer','min:1'],
            'days' => ['sometimes', 'array', 'max:30'],
            'days.*' => ['array:items'],
            'days.*.items' => ['present', 'array', 'max:50'],
            'days.*.items.*' => ['array:id,item_type,title,place_name,description,start_time,end_time,end_day_offset,source_place_id,estimated_cost,photo_reference'],
            'days.*.items.*.source_place_id' => ['sometimes','nullable',new ItineraryInteger,'integer','min:1'],
            'days.*.items.*.end_day_offset' => ['sometimes','integer','in:0,1'],
            'days.*.items.*.id' => ['nullable', new ItineraryInteger, 'integer', 'min:1'],
            'days.*.items.*.item_type' => ['required', Rule::in(['hotel', 'restaurant', 'attraction', 'activity', 'transport', 'shopping'])],
            'days.*.items.*.title' => ['required', 'string', 'max:200'],
            'days.*.items.*.place_name' => ['nullable', 'string', 'max:255'],
            'days.*.items.*.description' => ['nullable', 'string', 'max:5000'],
            'days.*.items.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.items.*.end_time' => ['nullable', 'date_format:H:i'],
            'days.*.items.*.estimated_cost' => ['nullable', new ItineraryInteger, 'integer', 'min:0', 'max:99999999'],
            'days.*.items.*.photo_reference' => ['sometimes', 'array:message_id,day_number,item_index'],
            'days.*.items.*.photo_reference.message_id' => ['required_with:days.*.items.*.photo_reference', new ItineraryInteger, 'integer', 'min:1'],
            'days.*.items.*.photo_reference.day_number' => ['required_with:days.*.items.*.photo_reference', new ItineraryInteger, 'integer', 'min:1', 'max:30'],
            'days.*.items.*.photo_reference.item_index' => ['required_with:days.*.items.*.photo_reference', new ItineraryInteger, 'integer', 'min:0', 'max:49'],
        ];
    }

    public function rules(): array
    {
        return [...$this->mutationRules(), ...self::tripRules()];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) return;
            if ($this->input('stay_mode') === 'day_trip' && $this->integer('duration_days') !== 1) {
                $validator->errors()->add('duration_days', 'Đi trong ngày chỉ có 1 ngày.');
            }
            if ($this->has('days') && (! array_is_list($this->input('days')) || count($this->input('days')) !== $this->integer('duration_days'))) {
                $validator->errors()->add('days', 'Số ngày trong lịch phải khớp thông tin chuyến đi.');
            }
            $ids = [];
            foreach ($this->input('days', []) as $day => $data) {
                if (! array_is_list($data['items'])) $validator->errors()->add("days.$day.items", 'Hoạt động phải là danh sách.');
                foreach ($data['items'] as $index => $item) {
                    if (! empty($item['id'])) {
                        if (in_array($item['id'], $ids, true)) $validator->errors()->add("days.$day.items.$index.id", 'Hoạt động bị trùng.');
                        $ids[] = $item['id'];
                    }
                    if (! empty($item['start_time']) && ! empty($item['end_time']) && empty($item['end_day_offset']) && $item['end_time'] <= $item['start_time']) {
                        $validator->errors()->add("days.$day.items.$index.end_time", 'Giờ kết thúc phải sau giờ bắt đầu.');
                    }
                }
            }
        });
    }
}
