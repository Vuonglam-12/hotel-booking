<?php

namespace App\Services\Itinerary;

use App\Models\Customer;
use App\Models\Itinerary;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class TripService
{
    public function owned(Customer $customer, int $id, bool $lock = false): Itinerary
    {
        return Itinerary::where('customer_id', $customer->id)->where('schema_version', 2)
            ->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
    }

    public function create(Customer $customer, array $data): Itinerary
    {
        $trip = new Itinerary;
        $trip->forceFill(['customer_id' => $customer->id, 'schema_version' => 2, 'revision' => 1, 'status' => 'draft']);
        $this->fill($trip, $data);
        $trip->save();
        $this->calendar($trip, $data);

        return $trip->refresh();
    }

    public function update(Customer $customer, int $id, array $data): Itinerary
    {
        $trip = $this->owned($customer, $id, true);
        app(WriteGuard::class)->revision($trip, $data['expected_revision']);
        abort_unless($trip->status === 'draft', 409, 'Chuyển về bản nháp trước khi sửa thông tin chuyến đi.');
        // Phase 1 cannot silently discard generated activities, quotes or snapshots.
        foreach (['itinerary_item', 'itinerary_place_snapshot', 'itinerary_cost_line', 'itinerary_lodging_room'] as $table) {
            abort_if(DB::table($table)->where('itinerary_id', $trip->id)->exists(), 409, 'Chuyến đi có dữ liệu cần kiểm tra lại ở giai đoạn tích hợp.');
        }
        $this->fill($trip, $data);
        $trip->revision++;
        $trip->save();
        $this->calendar($trip, $data);

        return $trip->refresh();
    }

    public function status(Customer $customer, int $id, array $data): Itinerary
    {
        $trip = $this->owned($customer, $id, true);
        app(WriteGuard::class)->revision($trip, $data['expected_revision']);
        $trip->forceFill(['status' => $data['status'], 'revision' => $trip->revision + 1, 'updated_at' => now()])->save();

        return $trip;
    }

    public function delete(Customer $customer, int $id, int $revision): void
    {
        $trip = $this->owned($customer, $id, true);
        app(WriteGuard::class)->revision($trip, $revision);
        $trip->delete();
    }

    private function fill(Itinerary $trip, array $data): void
    {
        abort_unless(app(DestinationService::class)->supported($data['location_id']), 422, 'Điểm đến chưa được hỗ trợ.');
        $values = [];
        foreach (['location_id', 'start_date', 'end_date', 'adults', 'children', 'infants', 'stay_mode', 'estimated_budget',
            'arrival_time', 'departure_time', 'activity_window_start', 'activity_window_end', 'activity_window_confirmed',
            'lodging_check_in', 'lodging_check_out', 'hotel_id', 'contingency_amount', 'transport', 'pace', 'requirements'] as $field) {
            $values[$field] = $data[$field] ?? null;
        }
        $values['interests'] = $data['interests'] ?? [];
        if(($values['pace'] ?? null)==='normal')$values['pace']='moderate';
        $values['food_restrictions'] = $data['food_restrictions'] ?? [];
        $values['title'] = $data['title'] ?? mb_substr('Chuyến đi '.Location::findOrFail($data['location_id'])->name, 0, 200);
        $values['total_days'] = CarbonImmutable::parse($data['start_date'])->diffInDays(CarbonImmutable::parse($data['end_date'])) + 1;
        $values['updated_at'] = now();
        $trip->forceFill($values);
    }

    private function calendar(Itinerary $trip, array $data): void
    {
        $allowances = array_column($data['free_day_allowances'] ?? [], 'amount', 'date');
        $dates = [];
        for ($date = CarbonImmutable::parse($data['start_date']); $date->toDateString() <= $data['end_date']; $date = $date->addDay()) {
            $iso = $date->toDateString();
            $dates[] = $iso;
            DB::table('itinerary_calendar_day')->updateOrInsert(['itinerary_id' => $trip->id, 'date' => $iso], [
                'selected_for_planning' => in_array($iso, $data['selected_dates'], true),
                'free_day_allowance' => $allowances[$iso] ?? null,
            ]);
        }
        DB::table('itinerary_calendar_day')->where('itinerary_id', $trip->id)->whereNotIn('date', $dates)->delete();
    }
}
