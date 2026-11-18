<?php

namespace App\Services;

use App\Support\RoomTypeV2Inventory;
use App\Support\RoomTypeV2Writer;
use Illuminate\Validation\ValidationException;

/** Test-only lifecycle adapter. Not registered with production controllers, callbacks or scheduler. */
final class BookingLifecycleServiceV2
{
    public function cancel(string $connection, int $id, int $customerId): array
    {
        return $this->locked($connection, $id, function ($session, $booking, $lines, $types) use ($customerId) {
            if ($booking->customer_id !== $customerId) throw ValidationException::withMessages(['customer_id' => 'Booking không thuộc khách hàng.']);
            RoomTypeV2Inventory::holding($booking->status);
            if ($booking->status === 'cancelled') return (array) $booking;
            if (!in_array($booking->status, ['pending', 'confirmed', 'expired'], true)) throw ValidationException::withMessages(['status' => 'Không thể hủy Booking ở trạng thái hiện tại.']);
            $hours = now()->diffInHours($booking->check_in, false);
            if ($hours < 24) throw ValidationException::withMessages(['check_in' => 'Không thể hủy sau hạn 24 giờ trước nhận phòng.']);
            $session->db->table('booking')->where('id', $booking->id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => 'customer']);
            \App\Support\RoomTypeV2BookingAudit::record($session->db,$booking->id,$booking->status,'cancelled','customer',$customerId,'customer_cancel');
            return (array) $session->db->table('booking')->where('id', $booking->id)->first();
        });
    }

    /** Same expiry semantics as Phase 1: pending past expiry becomes cancelled/system, not a new business status. */
    public function expire(string $connection, int $id): bool
    {
        return $this->locked($connection, $id, function ($session, $booking) {
            RoomTypeV2Inventory::holding($booking->status);
            if ($booking->status !== 'pending' || !$booking->expires_at || $booking->expires_at >= now()->format('Y-m-d H:i:s')) return false;
            $session->db->table('booking')->where('id', $booking->id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => 'system']);
            \App\Support\RoomTypeV2BookingAudit::record($session->db,$booking->id,$booking->status,'cancelled','system',null,'pending_expired');
            return true;
        });
    }

    /** Explicit Admin transition prototype; restore checks inventory, never rewrites money snapshots. */
    public function transition(string $connection, int $id, string $target, ?int $adminId=null): array
    {
        return $this->locked($connection, $id, function ($session, $booking, $lines, $types) use ($target,$adminId) {
            if (!in_array($target, ['pending', 'confirmed', 'completed', 'cancelled'], true)) throw ValidationException::withMessages(['status' => 'Trạng thái đích không được hỗ trợ.']);
            RoomTypeV2Inventory::holding($booking->status);
            if ($booking->status === $target) return (array) $booking;
            if (config('roomtype-v2.final_integration',false)) {
                if (!$adminId || !$session->db->table('customer')->where('id',$adminId)->where('role','admin')->exists()) throw ValidationException::withMessages(['admin'=>'Admin verification required.']);
                if (in_array($target,['pending','confirmed'],true)) throw ValidationException::withMessages(['status'=>'Use verified IPN/manual payment; released bookings require separate reconciliation, not a status override.']);
                if ($target==='completed' && $booking->status!=='confirmed') throw ValidationException::withMessages(['status'=>'Only a confirmed Booking can be completed.']);
            }
            RoomTypeV2Writer::duplicate($session->db, $booking, $target);
            if (RoomTypeV2Inventory::holding($target)) $this->verifyCommitment($session, $booking, $lines, $types, !RoomTypeV2Inventory::holding($booking->status));
            $session->db->table('booking')->where('id', $booking->id)->update(['status' => $target]);
            \App\Support\RoomTypeV2BookingAudit::record($session->db,$booking->id,$booking->status,$target,'admin',$adminId,'admin_status_change');
            return (array) $session->db->table('booking')->where('id', $booking->id)->first();
        });
    }

    /** Internal prototype only: the caller must already have verified gateway signature/amount.
     * No real gateway is called. Released orders are refused pending explicit late-payment policy approval.
     */
    public function verifiedPaymentSuccess(string $connection, int $id, int $paymentId, string $transactionId, string $amount): array
    {
        RoomTypeV2Inventory::open($connection);
        if (config('roomtype-v2.final_integration',false)) throw ValidationException::withMessages(['payment'=>'Unsigned internal payment prototype disabled; use verified IPN or Admin banking verification.']);
        return $this->locked($connection, $id, function ($session, $booking, $lines, $types) use ($paymentId, $transactionId, $amount) {
            if (!RoomTypeV2Inventory::holding($booking->status)) throw ValidationException::withMessages(['payment' => 'Late payment for a released Booking requires separate reconciliation; no automatic restore.']);
            $this->verifyCommitment($session, $booking, $lines, $types, false);
            $payment = $session->db->table('payment')->where('id', $paymentId)->where('booking_id', $booking->id)->lockForUpdate()->first();
            if (!$payment || $payment->transaction_id !== $transactionId || $payment->amount !== $amount || $amount !== $booking->total_price) {
                throw ValidationException::withMessages(['payment' => 'Giao dịch hoặc số tiền không khớp snapshot.']);
            }
            if ($payment->payment_status === 'success') return ['changed' => false, 'booking_status' => $booking->status];
            if ($payment->payment_status !== 'pending') throw ValidationException::withMessages(['payment' => 'Trạng thái thanh toán cần đối soát riêng.']);
            $session->db->table('payment')->where('id', $paymentId)->update(['payment_status' => 'success', 'paid_at' => now()]);
            if ($booking->status === 'pending') $session->db->table('booking')->where('id', $booking->id)->update(['status' => 'confirmed', 'confirmed_at' => now()]);
            return ['changed' => true, 'booking_status' => $booking->status === 'pending' ? 'confirmed' : $booking->status];
        });
    }

    /** Customer selection/acknowledgement only; no proof of payment, no extension or confirmation. */
    public function manualPayment(string $connection, int $id, int $customerId, string $method): array
    {
        return $this->locked($connection, $id, function ($session, $booking, $lines, $types) use ($customerId, $method) {
            if (config('roomtype-v2.final_integration',false) && $method!=='banking') throw ValidationException::withMessages(['payment'=>'Only QR banking acknowledgement is manual; VNPay must use verified IPN.']);
            if ($booking->customer_id !== $customerId || !in_array($method, ['cash', 'banking'], true)
                || $booking->status!=='pending' || !$booking->expires_at || $booking->expires_at<=now()->format('Y-m-d H:i:s')) throw ValidationException::withMessages(['payment' => 'Không thể ghi thanh toán thủ công cho đơn này.']);
            $this->verifyCommitment($session, $booking, $lines, $types, false);
            $q = $session->db->table('payment')->where('booking_id', $booking->id)->lockForUpdate();
            $payments = $q->get();
            if ($payments->contains('payment_status','success')) {
                throw ValidationException::withMessages(['payment' => 'Không ghi đè thanh toán thành công hoặc nhiều giao dịch cần đối soát.']);
            }
            $pending=$payments->where('payment_method',$method)->where('payment_status','pending');
            if ($pending->count()>1) throw ValidationException::withMessages(['payment'=>'Multiple pending attempts need reconciliation.']);
            if ($pending->isEmpty()) $session->db->table('payment')->insert(['booking_id'=>$booking->id,'amount'=>$booking->total_price,'payment_method'=>$method,
                'payment_status'=>'pending','created_at'=>now(),'transaction_id'=>'MANUAL_'.$booking->id.'_'.bin2hex(random_bytes(12))]);
            return (array) $session->db->table('booking')->where('id', $booking->id)->first();
        });
    }

    private function locked(string $connection, int $id, callable $callback): mixed
    {
        return RoomTypeV2Writer::run($connection, $id, $callback);
    }

    private function verifyCommitment($session, object $booking, $lines, array $types, bool $restoring): void
    {
        RoomTypeV2Writer::commitment($session, $booking, $lines, $types, $restoring);
    }
}
