<?php

namespace App\Services;

use App\Support\RoomTypeV2Inventory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Phase 2B.2 test-only write module. Production BookingService is untouched. */
final class BookingServiceV2
{
    public function create(string $connection, int $customerId, array $data): array
    {
        \App\Support\RoomTypeV2CheckoutGate::assertWriteAllowed();
        $demo=config('booking-demo.enabled')===true;
        if ($demo) \App\Support\BookingDemo::connection(); // Separate owned-instance gate; never fabricate a bank account.
        $session = RoomTypeV2Inventory::open($connection);
        if (config('roomtype-v2.final_integration',false)) $data['payment_method']=$data['payment_method']??'banking';
        $integer = function ($attribute, $value, $fail) {
            if (!is_int($value) && !(is_string($value) && ctype_digit($value))) $fail('Phải là số nguyên.');
        };
        Validator::make($data, [
            'hotel_id' => ['required', 'integer', 'min:1', $integer],
            'room_type_id' => ['required', 'integer', 'min:1', $integer],
            'quantity' => ['required', 'integer', 'between:1,10', $integer],
            'num_guests' => ['required', 'integer', 'min:1', 'max:2147483647', $integer],
            'check_in' => 'required|date_format:Y-m-d|after_or_equal:today',
            'check_out' => 'required|date_format:Y-m-d|after:check_in',
            'payment_method' => $demo?'required|in:banking':(config('roomtype-v2.final_integration',false)?'required|in:banking,vnpay':'nullable|in:cash,banking,vnpay'),
            'room_id' => 'prohibited', 'room_types' => 'prohibited',
            'special_request' => 'nullable|string|max:500',
        ])->validate();
        if (!$demo && config('roomtype-v2.final_integration', false)) {
            $paymentMethod = $data['payment_method'] ?? 'banking';
            if ($paymentMethod === 'banking' && !\App\Support\RoomTypeV2PaymentConfiguration::banking()) {
                throw ValidationException::withMessages(['payment_method' => 'Thông tin QR Banking chưa được xác minh; chưa thể nhận Booking thanh toán Banking.']);
            }
            if ($paymentMethod === 'vnpay' && \App\Support\RoomTypeV2PaymentConfiguration::vnpay()['status'] !== 'CONFIGURED') {
                throw ValidationException::withMessages(['payment_method' => 'VNPay sandbox chưa được xác minh; hãy chọn phương thức thanh toán khả dụng khác.']);
            }
        }
        $nights = RoomTypeV2Inventory::dates($data['check_in'], $data['check_out']);
        $db = $session->db;
        // Must own the outer transaction. No external side effects run in or after this module.
        if ($db->transactionLevel() !== 0) throw new \RuntimeException('V2 create requires its own transaction boundary.');
        return $db->transaction(function () use ($session, $db, $customerId, $data, $nights) {
            $hotelId = (int) $data['hotel_id'];
            $typeId = (int) $data['room_type_id'];
            $quantity = (int) $data['quantity'];
            $hotel = $session->hotel($hotelId, true);
            $type = $session->types($hotelId, [$typeId], true)[$typeId];
            if ($hotel->status !== 'active') throw ValidationException::withMessages(['hotel_id' => 'Khách sạn hiện không nhận đặt phòng.']);
            if ($type->status !== 'active') throw ValidationException::withMessages(['room_type_id' => 'Loại phòng đã ngừng bán.']);
            if ($data['num_guests'] > $type->capacity * $quantity) throw ValidationException::withMessages(['num_guests' => 'Số khách vượt sức chứa của số phòng đã chọn.']);
            if (!$db->table('customer')->where('id', $customerId)->exists()) throw ValidationException::withMessages(['customer_id' => 'Khách hàng không tồn tại.']);
            $availability = $session->inspect($type, $data['check_in'], $data['check_out'], null, true);
            if ($availability['available_rooms'] < $quantity) throw ValidationException::withMessages(['quantity' => 'Không đủ phòng trong toàn bộ khoảng lưu trú.']);
            $duplicate = $db->table('booking')->where('customer_id', $customerId)->where('hotel_id', $hotelId)
                ->where('check_in', $data['check_in'])->where('check_out', $data['check_out'])->whereIn('status', ['pending', 'confirmed'])->lockForUpdate()->first();
            if ($duplicate) throw ValidationException::withMessages(['booking' => 'Bạn đã có yêu cầu đặt phòng trùng ngày.']);
            $cents = RoomTypeV2Inventory::cents($type->base_price);
            $factor = $nights * $quantity;
            if ($cents > intdiv(999999999999, $factor)) throw ValidationException::withMessages(['total_price' => 'Tổng tiền vượt DECIMAL(12,2).']);
            $total = RoomTypeV2Inventory::money($cents * $factor);
            $method = $data['payment_method'] ?? 'cash';
            $id = $db->table('booking')->insertGetId([
                'customer_id' => $customerId, 'hotel_id' => $hotelId, 'check_in' => $data['check_in'], 'check_out' => $data['check_out'],
                'num_guests' => (int) $data['num_guests'], 'total_price' => $total, 'status' => 'pending',
                'expires_at' => now()->addMinutes(15), 'special_request' => $data['special_request'] ?? null, 'created_at'=>now(),
            ]);
            $db->table('booking_room')->insert(['booking_id' => $id, 'room_type_id' => $typeId, 'room_id' => null,
                'quantity' => $quantity, 'nights' => $nights, 'price_at_booking' => $type->base_price]);
            // Policy C: selecting a payment method never proves receipt of funds.
            $db->table('payment')->insert(['booking_id' => $id, 'amount' => $total, 'payment_method' => $method,
                'payment_status' => 'pending', 'created_at'=>now(), 'transaction_id' => (config('booking-demo.enabled')?'DEMO_':($method==='vnpay'?'V2_':'MANUAL_')) . $id . '_' . bin2hex(random_bytes(12))]);
            \App\Support\RoomTypeV2BookingAudit::record($db,$id,null,'pending','customer',$customerId,'booking_created');
            return (array) $db->table('booking')->where('id', $id)->first();
        },3);
    }
}
