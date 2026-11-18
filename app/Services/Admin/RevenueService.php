<?php

namespace App\Services\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RevenueService
{
    private const TIMEZONE = 'Asia/Ho_Chi_Minh';

    public function getSummary(array $input = []): array
    {
        $data = Validator::make($input, [
            'period' => 'sometimes|required|in:today,7days,30days,month,year,custom',
            'date_from' => 'required_if:period,custom|nullable|date_format:Y-m-d',
            'date_to' => 'required_if:period,custom|nullable|date_format:Y-m-d|after_or_equal:date_from',
            'payment_scope' => 'sometimes|required|in:real,demo',
            'page' => 'sometimes|required|integer|min:1',
        ], [
            'period.in' => 'Kỳ báo cáo không hợp lệ.',
            'date_from.required_if' => 'Bạn chưa chọn ngày bắt đầu báo cáo.',
            'date_to.required_if' => 'Bạn chưa chọn ngày kết thúc báo cáo.',
            'date_from.date_format' => 'Ngày bắt đầu phải có định dạng năm-tháng-ngày.',
            'date_to.date_format' => 'Ngày kết thúc phải có định dạng năm-tháng-ngày.',
            'date_to.after_or_equal' => 'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.',
            'payment_scope.in' => 'Nguồn thanh toán không hợp lệ.',
            'page.min' => 'Trang giao dịch phải từ 1 trở lên.',
        ])->validate();
        [$start, $end] = $this->range($data);
        $scope = $data['payment_scope'] ?? 'real';
        $legacySummary = $input === [];

        // Every panel reads the same snapshot. Reporting never changes bookings or payments.
        return DB::transaction(function () use ($data, $start, $end, $scope, $legacySummary) {
            $offset = (int) DB::selectOne('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS offset_seconds')->offset_seconds;
            $shift = $start->getOffset() - $offset;
            $localDate = 'DATE_ADD(p.paid_at, INTERVAL ' . $shift . ' SECOND)';
            $base = $this->payments($scope)
                ->where('p.paid_at', '>=', $start->utc()->addSeconds($offset)->format('Y-m-d H:i:s'))
                ->where('p.paid_at', '<', $end->addDay()->utc()->addSeconds($offset)->format('Y-m-d H:i:s'));
            $totals = (clone $base)->selectRaw('COALESCE(SUM(p.amount), 0) AS amount, COUNT(*) AS transactions, COUNT(DISTINCT b.hotel_id) AS hotels')->first();
            $months = (clone $base)->selectRaw("DATE_FORMAT($localDate, '%Y-%m') AS bucket, SUM(p.amount) AS total, COUNT(*) AS count")
                ->groupBy('bucket')->orderBy('bucket')->get()->keyBy('bucket');
            $monthly = [];
            for ($month = $start->startOfMonth(); $month->lte($end); $month = $month->addMonth()) {
                $key = $month->format('Y-m');
                $monthly[] = ['year' => $month->year, 'month' => $month->month, 'label' => $month->format('m/Y'),
                    'total' => $this->money($months->get($key)?->total ?? 0), 'count' => (int) ($months->get($key)?->count ?? 0)];
            }
            $daily = $start->diffInDays($end) <= 62;
            if ($daily) {
                $groups = (clone $base)->selectRaw("DATE($localDate) AS bucket, SUM(p.amount) AS total, COUNT(*) AS count")
                    ->groupBy('bucket')->orderBy('bucket')->get()->keyBy('bucket');
                $series = [];
                for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                    $value = $groups->get($day->toDateString());
                    $series[] = ['date' => $day->toDateString(), 'label' => $day->format('d/m/Y'),
                        'total' => $this->money($value?->total ?? 0), 'count' => (int) ($value?->count ?? 0)];
                }
            } else {
                $series = array_map(fn ($month) => ['date' => sprintf('%04d-%02d-01', $month['year'], $month['month'])] + $month, $monthly);
            }
            $top = (clone $base)->selectRaw('h.id, h.name, SUM(p.amount) AS revenue, COUNT(*) AS transactions')
                ->groupBy('h.id', 'h.name')->orderByDesc('revenue')->orderBy('h.id')->limit(5)->get();
            $methods = (clone $base)->selectRaw("COALESCE(NULLIF(p.payment_method, ''), 'unknown') AS method, SUM(p.amount) AS total, COUNT(*) AS count")
                ->groupBy('method')->orderByDesc('total')->get();
            $currentPage = min((int) ($data['page'] ?? 1), max(1, (int) ceil((int) $totals->transactions / 20)));
            $transactions = (clone $base)->leftJoin('customer as c', 'c.id', '=', 'b.customer_id')
                ->select(['p.id', 'p.booking_id', 'p.amount', 'p.payment_method', 'p.payment_status', 'b.status AS booking_status',
                    'c.name AS customer_name', 'h.name AS hotel_name'])
                ->selectRaw("DATE_FORMAT($localDate, '%Y-%m-%d %H:%i:%s') AS paid_at")
                ->orderByDesc('p.paid_at')->orderByDesc('p.id')->paginate(20, ['*'], 'page', $currentPage);
            $lastMonth = $monthly[count($monthly) - 1];

            return [
                'period' => ['key' => $data['period'] ?? 'year', 'date_from' => $start->toDateString(), 'date_to' => $end->toDateString(),
                    'label' => $start->format('d/m/Y') . ' – ' . $end->format('d/m/Y'), 'timezone' => self::TIMEZONE,
                    'payment_scope' => $scope, 'scope_label' => $scope === 'demo' ? 'Thanh toán DEMO' : 'Thanh toán thực tế'],
                'currency' => 'VND', 'generated_at' => CarbonImmutable::now(self::TIMEZONE)->toIso8601String(),
                'total_revenue' => $this->money($totals->amount), 'total_transactions' => (int) $totals->transactions, 'hotel_count' => (int) $totals->hotels,
                'last_month_revenue' => $lastMonth['total'], 'last_month_label' => $lastMonth['label'],
                // The customer sidebar's existing no-filter call expects months with actual receipts.
                'monthly_revenue' => $legacySummary ? array_values(array_filter($monthly, fn ($month) => $month['count'] > 0)) : $monthly,
                'series' => $series, 'group_by' => $daily ? 'day' : 'month',
                'top_hotels' => $top, 'payment_methods' => $methods, 'room_types' => $this->roomTypes($base), 'services' => $this->services($base),
                'transactions' => ['data' => $transactions->items(), 'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(), 'total' => $transactions->total(), 'from' => $transactions->firstItem(), 'to' => $transactions->lastItem()],
            ];
        });
    }

    private function range(array $data): array
    {
        $today = CarbonImmutable::today(self::TIMEZONE);
        [$from, $to] = match ($data['period'] ?? 'year') {
            'today' => [$today, $today], '7days' => [$today->subDays(6), $today], '30days' => [$today->subDays(29), $today],
            'month' => [$today->startOfMonth(), $today], 'year' => [$today->startOfYear(), $today],
            'custom' => [CarbonImmutable::parse($data['date_from'], self::TIMEZONE)->startOfDay(), CarbonImmutable::parse($data['date_to'], self::TIMEZONE)->startOfDay()],
        };
        if ($from->diffInDays($to) > 365) throw ValidationException::withMessages(['date_to' => 'Mỗi báo cáo tối đa 366 ngày. Vui lòng rút ngắn khoảng ngày.']);
        return [$from, $to];
    }

    private function payments(string $scope): Builder
    {
        $query = DB::table('payment as p')->join('booking as b', 'b.id', '=', 'p.booking_id')->join('hotel as h', 'h.id', '=', 'b.hotel_id')
            ->where('p.payment_status', 'success')->whereNotNull('p.paid_at')->where('p.amount', '>', 0)
            ->whereIn('b.status', ['confirmed', 'completed'])->whereColumn('p.amount', 'b.total_price');
        $this->paymentScope($query, $scope, 'p');
        // Deduplicate across all dates, not only inside this report's period.
        return $query->whereNotExists(function (Builder $other) use ($scope) {
            $other->selectRaw('1')->from('payment as earlier')->whereColumn('earlier.booking_id', 'p.booking_id')
                ->where('earlier.payment_status', 'success')->whereNotNull('earlier.paid_at')->whereColumn('earlier.amount', 'b.total_price')
                ->where(fn ($q) => $q->whereColumn('earlier.paid_at', '<', 'p.paid_at')
                    ->orWhere(fn ($same) => $same->whereColumn('earlier.paid_at', 'p.paid_at')->whereColumn('earlier.id', '<', 'p.id')));
            $this->paymentScope($other, $scope, 'earlier');
        });
    }

    private function paymentScope(Builder $query, string $scope, string $alias): void
    {
        $hasDemoBookings = Schema::hasTable('booking_demo');
        $query->where(function (Builder $match) use ($scope, $alias, $hasDemoBookings) {
            if ($scope === 'demo') {
                $match->where($alias . '.payment_method', 'demo')->orWhere($alias . '.transaction_id', 'like', 'DEMO%');
                if ($hasDemoBookings) $match->orWhereExists(fn ($q) => $q->selectRaw('1')->from('booking_demo as demo_scope')->whereColumn('demo_scope.booking_id', $alias . '.booking_id'));
            } else {
                $match->where(fn ($q) => $q->whereNull($alias . '.payment_method')->orWhere($alias . '.payment_method', '!=', 'demo'))
                    ->where(fn ($q) => $q->whereNull($alias . '.transaction_id')->orWhere($alias . '.transaction_id', 'not like', 'DEMO%'));
                if ($hasDemoBookings) $match->whereNotExists(fn ($q) => $q->selectRaw('1')->from('booking_demo as demo_scope')->whereColumn('demo_scope.booking_id', $alias . '.booking_id'));
            }
        });
    }

    private function roomTypes(Builder $base): array
    {
        $lines = (clone $base)->join('booking_room as br', 'br.booking_id', '=', 'b.id')->leftJoin('room_type as rt', 'rt.id', '=', 'br.room_type_id');
        $name = "COALESCE(rt.name, 'Loại phòng đã lưu')";
        if (Schema::hasTable('hotel_room_type')) {
            $lines->leftJoin('hotel_room_type as hrt', fn ($join) => $join->on('hrt.hotel_id', '=', 'b.hotel_id')->on('hrt.room_type_id', '=', 'br.room_type_id'));
            $name = "COALESCE(hrt.name, rt.name, 'Loại phòng đã lưu')";
        }
        $amount = 'br.price_at_booking * br.nights * br.quantity';
        if (Schema::hasTable('booking_nightly_rate')) {
            $snapshots = DB::table('booking_nightly_rate')->selectRaw('booking_room_id, SUM(unit_price) AS unit_total, COUNT(*) AS nights')->groupBy('booking_room_id');
            $lines->leftJoinSub($snapshots, 'saved_nights', 'saved_nights.booking_room_id', '=', 'br.id');
            $amount = "CASE WHEN saved_nights.nights = br.nights THEN saved_nights.unit_total * br.quantity ELSE $amount END";
        }
        return $lines->selectRaw("b.hotel_id, br.room_type_id, h.name AS hotel_name, $name AS name, SUM($amount) AS total, COUNT(DISTINCT b.id) AS bookings")
            ->groupBy('b.hotel_id', 'br.room_type_id', 'h.name')->groupByRaw($name)->orderByDesc('total')
            ->orderBy('b.hotel_id')->orderBy('br.room_type_id')->limit(8)->get()->toArray();
    }

    private function services(Builder $base): array
    {
        if (!Schema::hasTable('booking_service') || !Schema::hasTable('service')) return ['available' => false, 'items' => [], 'total' => '0.00'];
        $query = (clone $base)->join('booking_service as bs', 'bs.booking_id', '=', 'b.id')->leftJoin('service as s', 's.id', '=', 'bs.service_id');
        $total = (clone $query)->selectRaw('COALESCE(SUM(bs.price_at_booking * bs.quantity), 0) AS total')->first()->total;
        $items = $query->selectRaw("bs.service_id, COALESCE(s.name, 'Dịch vụ đã lưu') AS name, SUM(bs.price_at_booking * bs.quantity) AS total")
            ->groupBy('bs.service_id', 's.name')->orderByDesc('total')->orderBy('bs.service_id')->limit(8)->get();
        return ['available' => true, 'items' => $items, 'total' => $this->money($total)];
    }

    private function money($amount): string
    {
        // SQL sums DECIMAL; preserve cents when serializing.
        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '');
        return $whole . '.' . str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
