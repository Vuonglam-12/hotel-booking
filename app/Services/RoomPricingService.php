<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Prices for the currently deployed hotel-scoped catalog. Does not activate V2 or a gateway. */
final class RoomPricingService
{
    private const MAX_CENTS = 999999999999;

    public function quote(int $hotelId, int $typeId, string $checkIn, string $checkOut, int $quantity = 1): array
    {
        Validator::make(compact('checkIn', 'checkOut', 'quantity'), [
            'checkIn' => 'required|date_format:Y-m-d',
            'checkOut' => 'required|date_format:Y-m-d|after:checkIn',
            'quantity' => 'required|integer|between:1,10',
        ])->validate();
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $checkIn);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $checkOut);
        $nights = (int) $start->diffInDays($end);
        if ($nights < 1 || $nights > 365) {
            throw ValidationException::withMessages(['check_out' => 'Kỳ lưu trú từ 1 đến 365 đêm.']);
        }

        return DB::transaction(function () use ($hotelId, $typeId, $checkIn, $checkOut, $quantity, $start, $nights) {
            // The same parent lock serializes booking creation and Admin price edits.
            Hotel::whereKey($hotelId)->lockForUpdate()->firstOrFail();
            $type = $this->ownedType($hotelId, $typeId);
            if ($type->base_price===null) {
                throw ValidationException::withMessages(['base_price'=>'Giá phòng chưa nhập. Vui lòng cập nhật giá cơ bản trước khi nhận đặt phòng.']);
            }
            $base = (string) $type->base_price;
            $rates = Schema::hasTable('hotel_room_rate')
                ? DB::table('hotel_room_rate')->where('hotel_id', $hotelId)->where('room_type_id', $typeId)
                    ->where('start_date', '<', $checkOut)->where('end_date', '>=', $checkIn)->orderBy('id')->lockForUpdate()->get()
                : collect();
            $nightly = [];
            $sum = 0;
            for ($i = 0; $i < $nights; $i++) {
                $date = $start->addDays($i)->toDateString();
                $applicable = $rates->filter(fn ($rate) => $rate->start_date <= $date && $rate->end_date >= $date);
                foreach (['day', 'season'] as $kind) {
                    if ($applicable->where('kind', $kind)->count() > 1) {
                        throw ValidationException::withMessages(['price' => 'Bảng giá có các khoảng cùng ưu tiên chồng chéo. Vui lòng liên hệ quản trị viên.']);
                    }
                }
                $rate = $applicable->firstWhere('kind', 'day') ?? $applicable->firstWhere('kind', 'season');
                $baseCents = $this->cents($base);
                $mode = $rate ? ($rate->amount_mode ?? 'replacement') : 'surcharge';
                $surcharge = $rate && $mode === 'surcharge' ? $this->cents((string) $rate->price, true) : 0;
                $price = $rate && $mode === 'replacement' ? $this->cents((string) $rate->price) : $baseCents + $surcharge;
                $sum += $price;
                if ($sum > intdiv(self::MAX_CENTS, $quantity)) {
                    throw ValidationException::withMessages(['total_price' => 'Tổng tiền vượt giới hạn lưu trữ.']);
                }
                $nightly[] = ['date' => $date, 'unit_price' => $this->decimal($price), 'source' => $rate?->kind ?? 'base',
                    'rate_id' => $rate ? (int) $rate->id : null, 'label' => $rate?->label,
                    'amount_mode' => $mode, 'base_price' => $mode === 'surcharge' ? $this->decimal($baseCents) : null,
                    'surcharge' => $mode === 'surcharge' ? $this->decimal($surcharge) : null];
            }
            $quote = ['currency' => 'VND', 'nightly_rates' => $nightly, 'nights' => $nights, 'quantity' => $quantity,
                'unit_total' => $this->decimal($sum), 'room_subtotal' => $this->decimal($sum * $quantity), 'total_price' => $this->decimal($sum * $quantity),
                'average_nightly_price' => $this->decimal(intdiv($sum + intdiv($nights, 2), $nights))];
            $quote['quote_hash'] = hash('sha256', json_encode([$hotelId, $typeId, $checkIn, $checkOut, $quantity, $nightly], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return $quote;
        }, 3);
    }

    public function rates(int $hotelId, int $typeId): array
    {
        $this->ownedType($hotelId, $typeId);
        return DB::table('hotel_room_rate')->where('hotel_id', $hotelId)->where('room_type_id', $typeId)
            ->orderBy('start_date')->orderBy('kind')->get()->map(fn ($rate) => (array) $rate)->all();
    }

    public function saveRate(int $hotelId, int $typeId, array $input, ?int $rateId = null): array
    {
        $data = Validator::make($input, [
            'kind' => 'required|in:day,season', 'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'price' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D'], 'label' => 'nullable|string|max:150',
            'amount_mode' => 'sometimes|required|in:surcharge,replacement',
        ])->validate();
        if ($data['kind'] === 'day' && $data['start_date'] !== $data['end_date']) {
            throw ValidationException::withMessages(['end_date' => 'Giá ngày cụ thể phải có ngày bắt đầu và kết thúc giống nhau.']);
        }
        return DB::transaction(function () use ($hotelId, $typeId, $rateId, $data) {
            Hotel::whereKey($hotelId)->lockForUpdate()->firstOrFail();
            $this->ownedType($hotelId, $typeId);
            $existing = null;
            if ($rateId !== null) {
                $existing = DB::table('hotel_room_rate')->where('id', $rateId)->where('hotel_id', $hotelId)->where('room_type_id', $typeId)->lockForUpdate()->first();
                abort_unless($existing, 404);
            }
            // Omitted mode must never silently convert an existing legacy price.
            $data['amount_mode'] = $data['amount_mode'] ?? ($existing->amount_mode ?? 'surcharge');
            $data['price'] = $this->decimal($this->cents((string) $data['price'], $data['amount_mode'] === 'surcharge'));
            $overlap = DB::table('hotel_room_rate')->where('hotel_id', $hotelId)->where('room_type_id', $typeId)
                ->where('kind', $data['kind'])->where('start_date', '<=', $data['end_date'])->where('end_date', '>=', $data['start_date'])
                ->when($rateId !== null, fn ($query) => $query->where('id', '!=', $rateId))->lockForUpdate()->first();
            if ($overlap) {
                throw ValidationException::withMessages(['start_date' => 'Khoảng giá cùng ưu tiên đã tồn tại và chồng chéo với khoảng này.']);
            }
            $values = $data + ['updated_at' => now()];
            if ($rateId === null) $rateId = DB::table('hotel_room_rate')->insertGetId($values + ['hotel_id' => $hotelId, 'room_type_id' => $typeId, 'created_at' => now()]);
            else DB::table('hotel_room_rate')->where('id', $rateId)->update($values);
            return (array) DB::table('hotel_room_rate')->where('id', $rateId)->first();
        }, 3);
    }

    public function deleteRate(int $hotelId, int $typeId, int $rateId): void
    {
        DB::transaction(function () use ($hotelId, $typeId, $rateId) {
            Hotel::whereKey($hotelId)->lockForUpdate()->firstOrFail();
            $this->ownedType($hotelId, $typeId);
            $rate = DB::table('hotel_room_rate')->where('id', $rateId)->where('hotel_id', $hotelId)->where('room_type_id', $typeId)->lockForUpdate()->first();
            abort_unless($rate, 404);
            DB::table('hotel_room_rate')->where('id', $rateId)->delete();
        }, 3);
    }

    /** Called only while the booking insert transaction still owns the hotel lock. Never updates history. */
    public function saveSnapshot(int $bookingRoomId, array $quote): void
    {
        if (DB::connection()->transactionLevel() < 1) throw new \LogicException('Nightly snapshots require the booking transaction.');
        if (DB::table('booking_nightly_rate')->where('booking_room_id', $bookingRoomId)->exists()) {
            throw new \LogicException('Nightly booking prices are immutable.');
        }
        DB::table('booking_nightly_rate')->insert(array_map(fn ($night) => [
            'booking_room_id' => $bookingRoomId, 'night_date' => $night['date'], 'unit_price' => $night['unit_price'],
            'source' => $night['source'], 'rate_id' => $night['rate_id'], 'rate_label' => $night['label'] ?? null,
            'base_price' => $night['base_price'] ?? null, 'surcharge' => $night['surcharge'] ?? null,
            'amount_mode' => $night['amount_mode'] ?? null,
        ], $quote['nightly_rates']));
    }

    public function snapshot(int $bookingRoomId): array
    {
        if (!Schema::hasTable('booking_nightly_rate')) return [];
        return DB::table('booking_nightly_rate')->where('booking_room_id', $bookingRoomId)->orderBy('night_date')->get()
            ->map(fn ($night) => ['date' => $night->night_date, 'unit_price' => $night->unit_price,
                'source' => $night->source, 'rate_id' => $night->rate_id, 'label' => $night->rate_label,
                'base_price' => $night->base_price ?? null, 'surcharge' => $night->surcharge ?? null,
                'amount_mode' => $night->amount_mode ?? null])->all();
    }

    private function ownedType(int $hotelId, int $typeId): RoomType
    {
        Hotel::findOrFail($hotelId);
        $type = app(HotelRoomTypeService::class)->effective($hotelId, $typeId);
        $belongs = Room::where('hotel_id', $hotelId)->where('room_type_id', $typeId)->managed()->exists();
        if (!$belongs && HotelRoomTypeService::enabled()
            && \App\Models\HotelRoomType::where('hotel_id',$hotelId)->where('room_type_id',$typeId)->where('status','!=','archived')->exists()) {
            abort(404, 'Loại phòng chưa có phòng quản lý. Vui lòng cập nhật tổng số phòng trước khi sử dụng bảng giá.');
        }
        abort_unless($belongs, 404, 'Loại phòng không thuộc khách sạn này.');
        return $type;
    }

    private function cents(string $value, bool $allowZero = false): int
    {
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value)) {
            throw ValidationException::withMessages(['price' => 'Giá phòng phải hợp lệ, tối đa hai chữ số thập phân.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '');
        $amount = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        if ($amount < ($allowZero ? 0 : 1) || $amount > self::MAX_CENTS) throw ValidationException::withMessages(['price' => 'Giá phòng phải hợp lệ và trong giới hạn lưu trữ.']);
        return $amount;
    }

    private function decimal(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
