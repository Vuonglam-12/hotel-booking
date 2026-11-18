<?php

namespace App\Services\Itinerary;

use App\Models\Itinerary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class BudgetCalculator
{
    public function forTrip(Itinerary $trip): array
    {
        $days = DB::table('itinerary_calendar_day')->where('itinerary_id', $trip->id)->orderBy('date')->get()->map(fn ($row) => (array) $row)->all();
        $costs = DB::table('itinerary_cost_line')->where('itinerary_id', $trip->id)->get()->map(fn ($row) => (array) $row)->all();
        $rooms = DB::table('itinerary_lodging_room')->where('itinerary_id', $trip->id)->get()->map(fn ($row) => (array) $row)->all();

        return $this->calculate($trip, $days, $costs, $rooms);
    }

    public function calculate(Itinerary $trip, array $days, array $costs, array $rooms = []): array
    {
        $categories = [];
        foreach (['lodging', 'activities', 'free_days', 'contingency'] as $key) {
            $categories[$key] = ['known_amount' => 0, 'estimated_amount' => 0, 'unknown_count' => 0];
        }
        $reasons = [];
        $overflow = false;
        $lodgingLines = 0;
        $unknown = function (string $category, string $reason) use (&$categories, &$reasons) {
            $categories[$category]['unknown_count']++;
            $reasons[] = $reason;
        };
        $add = function (string $category, string $kind, int $amount, int $quantity = 1) use (&$categories, &$overflow) {
            $current = $categories[$category][$kind];
            if ($quantity < 1 || $current === null || $amount > intdiv(PHP_INT_MAX - $current, $quantity)) {
                $overflow = true;
                $categories[$category][$kind] = null;
            } else {
                $categories[$category][$kind] += $amount * $quantity;
            }
        };

        foreach ($costs as $cost) {
            $category = $cost['category'] === 'lodging' ? 'lodging' : 'activities';
            if ($category === 'lodging' && $trip->stay_mode !== 'aurel_hotel') {
                continue;
            }
            if ($category === 'lodging') {
                $lodgingLines++;
            }
            $amount = $this->amount($cost['unit_amount']);
            if (! in_array($cost['category'], ['lodging', 'activity'], true) || ! in_array($cost['price_status'], ['known', 'estimated'], true)
                || $amount === null || (int) $cost['quantity'] < 1 || $cost['currency'] !== 'VND'
                || ! in_array($cost['unit'], ['person', 'group', 'vehicle', 'room_night'], true) || empty($cost['source'])) {
                $unknown($category, 'cost_line:'.($cost['id'] ?? 'unknown'));

                continue;
            }
            $add($category, $cost['price_status'] === 'known' ? 'known_amount' : 'estimated_amount', $amount, (int) $cost['quantity']);
        }

        foreach ($days as $day) {
            if ($day['planning_status'] === 'planned') {
                // Once scheduled, that day's free allowance is replaced, never added.
                $hasPrice = array_filter($costs, fn ($cost) => $cost['category'] === 'activity' && (int) ($cost['calendar_day_id'] ?? 0) === (int) $day['id']);
                if (! $hasPrice) {
                    $unknown('activities', 'planned_day_without_prices:'.$day['date']);
                }

                continue;
            }
            $allowance = $this->amount($day['free_day_allowance']);
            if ($allowance === null) {
                $unknown('free_days', 'free_day_allowance:'.$day['date']);
            } else {
                $add('free_days', 'known_amount', $allowance);
            }
            if ($day['selected_for_planning']) {
                $unknown('activities', 'selected_day_not_generated:'.$day['date']);
            }
        }
        $contingency = $this->amount($trip->contingency_amount);
        if ($contingency === null) {
            $unknown('contingency', 'contingency_amount');
        } else {
            $add('contingency', 'known_amount', $contingency);
        }

        $uncovered = [];
        if ($trip->stay_mode === 'aurel_hotel') {
            $party = ['adults' => $trip->adults, 'children' => $trip->children, 'infants' => $trip->infants];
            for ($night = CarbonImmutable::parse($trip->start_date); $night->lt(CarbonImmutable::parse($trip->end_date)); $night = $night->addDay()) {
                $iso = $night->toDateString();
                $inRental = $trip->lodging_check_in && $trip->lodging_check_out && $iso >= $trip->lodging_check_in->toDateString() && $iso < $trip->lodging_check_out->toDateString();
                $covered = $trip->hotel_id && $inRental && array_filter($rooms, fn ($room) => $room['night'] === $iso
                    && (int) $room['quantity'] > 0 && ! empty($room['policy_verified_at']) && ! empty($room['policy_snapshot'])
                    && json_decode($room['verified_party'] ?? 'null', true) === $party);
                if (! $covered) {
                    $uncovered[] = $iso;
                    $unknown('lodging', 'uncovered_night:'.$iso);
                }
            }
            if (! $lodgingLines && $trip->total_days > 1) {
                $unknown('lodging', 'lodging_prices_unavailable');
            }
        }

        $known = 0;
        $estimated = 0;
        $missing = 0;
        foreach ($categories as $category) {
            $missing += $category['unknown_count'];
            foreach (['known_amount' => 'known', 'estimated_amount' => 'estimated'] as $field => $target) {
                if ($category[$field] === null || $$target > PHP_INT_MAX - $category[$field]) {
                    $overflow = true;
                } else {
                    $$target += $category[$field];
                }
            }
        }
        if ($known > PHP_INT_MAX - $estimated) {
            $overflow = true;
        }
        $total = $overflow ? null : $known + $estimated;
        $limit = $trip->estimated_budget === null ? null : (int) $trip->estimated_budget;
        $status = $limit === null ? 'incomplete' : ($overflow || $total > $limit ? 'over_budget' : ($missing ? 'incomplete' : ($estimated > 0 ? 'estimated_within_budget' : 'within_budget')));

        $warnings=[];
        if($limit!==null && $total!==null && $total>$limit)$warnings[]='Phần chi phí đã nhập hoặc có giá tham khảo vượt ngân sách '.number_format($total-$limit,0,',','.').' ₫. Chưa cộng các khoản còn thiếu.';
        if($missing)$warnings[]='Chưa đủ giá để xác định tổng chi phí chuyến đi; các khoản bỏ trống không được tính là miễn phí.';
        return ['currency' => 'VND', 'limit' => $limit,'warnings'=>$warnings,
            'over_budget_amount'=>$limit!==null && $total!==null?max(0,$total-$limit):null,
            'coverage_notice'=>'Các khoản ăn uống, lưu trú và di chuyển chưa có giá thì chưa được cộng vào tổng tạm tính.',
            'scope' => $trip->stay_mode === 'aurel_hotel' ? 'lodging_and_activities' : 'activities_only',
            'excludes' => ['travel_to_destination', 'travel_from_destination'],
            'categories' => $categories, 'known_total' => $overflow ? null : $known, 'estimated_total' => $overflow ? null : $estimated,
            'accounted_total' => $total, 'unknown_count' => $missing, 'complete' => $missing === 0 && ! $overflow,
            'status' => $status, 'calculation_overflow' => $overflow, 'unknown_reasons' => $reasons,
            'uncovered_nights' => $uncovered, 'feasibility_checked' => false];
    }

    private function amount(mixed $value): ?int
    {
        if ($value === null || ! preg_match('/^\d{1,10}(?:\.00)?$/D', (string) $value)) {
            return null;
        }

        return (int) $value;
    }
}
