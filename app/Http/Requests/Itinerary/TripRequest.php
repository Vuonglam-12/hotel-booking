<?php

namespace App\Http\Requests\Itinerary;

use App\Rules\ItineraryInteger;
use App\Services\Itinerary\DestinationService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class TripRequest extends MutationRequest
{
    public function rules(): array
    {
        $dayTrip = $this->input('stay_mode') === 'day_trip';
        $hotel = $this->input('stay_mode') === 'aurel_hotel';
        $integer = fn () => new ItineraryInteger;

        return [...$this->mutationRules(),
            'title' => ['sometimes', 'string', 'max:200'],
            'location_id' => ['bail', 'required', $integer(), 'integer', 'min:1', function ($attribute, $value, $fail) {
                if (! app(DestinationService::class)->supported($value)) {
                    $fail('Điểm đến không tồn tại hoặc chưa được hỗ trợ.');
                }
            }],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'adults' => ['bail', 'required', $integer(), 'integer', 'min:1', 'max:8'],
            'children' => ['present', 'array', 'max:7'], 'infants' => ['present', 'array', 'max:7'],
            'children.*' => ['array:age,unit'], 'infants.*' => ['array:age,unit'],
            'children.*.age' => ['bail', 'required', $integer(), 'integer', 'min:0'], 'infants.*.age' => ['bail', 'required', $integer(), 'integer', 'min:0'],
            'children.*.unit' => ['required', Rule::in(['years', 'months'])],
            'infants.*.unit' => ['required', Rule::in(['years', 'months'])],
            'stay_mode' => ['required', Rule::in(['aurel_hotel', 'own_accommodation', 'day_trip'])],
            'selected_dates' => ['required', 'array', 'min:1', 'max:5'],
            'selected_dates.*' => ['required', 'date_format:Y-m-d', 'distinct'],
            'arrival_time' => [Rule::requiredIf($dayTrip), 'nullable', 'date_format:H:i'],
            'departure_time' => [Rule::requiredIf($dayTrip), 'nullable', 'date_format:H:i'],
            'activity_window_start' => [Rule::prohibitedIf($dayTrip), 'nullable', 'required_with:activity_window_end', 'date_format:H:i'],
            'activity_window_end' => [Rule::prohibitedIf($dayTrip), 'nullable', 'required_with:activity_window_start', 'date_format:H:i', 'after:activity_window_start'],
            'activity_window_confirmed' => ['required', 'boolean'],
            'lodging_check_in' => [Rule::prohibitedIf(! $hotel), 'nullable', 'required_with:lodging_check_out,hotel_id', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before:end_date'],
            'lodging_check_out' => [Rule::prohibitedIf(! $hotel), 'nullable', 'required_with:lodging_check_in,hotel_id', 'date_format:Y-m-d', 'after:lodging_check_in', 'before_or_equal:end_date'],
            'hotel_id' => ['bail', Rule::prohibitedIf(! $hotel), 'nullable', $integer(), 'integer', Rule::exists('hotel', 'id')->where('location_id', is_int($this->input('location_id')) ? $this->input('location_id') : 0)->where('status', 'active')],
            'estimated_budget' => ['bail', 'required', $integer(), 'integer', 'min:1', 'max:9999999999'],
            'contingency_amount' => ['bail', 'nullable', $integer(), 'integer', 'min:0', 'max:9999999999'],
            'free_day_allowances' => ['sometimes', 'array', 'max:30'],
            'free_day_allowances.*' => ['array:date,amount'],
            'free_day_allowances.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'free_day_allowances.*.amount' => ['bail', 'present', 'nullable', $integer(), 'integer', 'min:0', 'max:9999999999'],
            'transport' => ['nullable', 'string', 'max:100'], 'pace' => ['nullable', Rule::in(['relaxed', 'normal', 'moderate', 'packed'])],
            'excluded_ids'=>['sometimes','array','max:500'],'excluded_ids.*'=>['regex:/^[A-Za-z0-9:_-]{1,100}$/D'],
            'interests' => ['sometimes', 'array', 'max:30'], 'interests.*' => ['required', 'string', 'max:50', 'distinct'],
            'food_restrictions' => ['sometimes', 'array', 'max:30'], 'food_restrictions.*' => ['required', 'string', 'max:100', 'distinct'],
            'requirements' => ['nullable', 'string', 'max:5000'], 'status' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (['children', 'infants', 'selected_dates', 'free_day_allowances', 'interests', 'food_restrictions'] as $field) {
                if (is_array($this->input($field)) && ! array_is_list($this->input($field))) {
                    $validator->errors()->add($field, 'Phải là danh sách JSON.');
                }
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->integer('adults') + count($this->input('children')) + count($this->input('infants')) > 8) {
                $validator->errors()->add('adults', 'Tổng người lớn, trẻ em và em bé tối đa 8 khách.');
            }
            $start = CarbonImmutable::parse($this->input('start_date'));
            $end = CarbonImmutable::parse($this->input('end_date'));
            if ($start->diffInDays($end) + 1 > 30) {
                $validator->errors()->add('end_date', 'Kỳ chuyến đi tối đa 30 ngày theo lịch.');
            }
            foreach ($this->input('selected_dates') as $index => $date) {
                if ($date < $this->input('start_date') || $date > $this->input('end_date')) {
                    $validator->errors()->add('selected_dates.'.$index, 'Ngày phải nằm trong kỳ chuyến đi.');
                }
            }
            foreach ($this->input('free_day_allowances', []) as $index => $allowance) {
                if ($allowance['date'] < $this->input('start_date') || $allowance['date'] > $this->input('end_date')) {
                    $validator->errors()->add('free_day_allowances.'.$index.'.date', 'Ngày phải nằm trong kỳ chuyến đi.');
                }
            }
            if ($this->input('stay_mode') === 'day_trip') {
                if ($start->ne($end)) {
                    $validator->errors()->add('end_date', 'Đi trong ngày phải bắt đầu và kết thúc cùng ngày.');
                }
                if ($this->input('departure_time') <= $this->input('arrival_time')) {
                    $validator->errors()->add('departure_time', 'Giờ kết thúc phải sau giờ bắt đầu.');
                }
            } elseif ($this->boolean('activity_window_confirmed') && (! $this->filled('activity_window_start') || ! $this->filled('activity_window_end'))) {
                $validator->errors()->add('activity_window_start', 'Phải nhập khung giờ dự kiến trước khi xác nhận.');
            }
        });
    }
}
