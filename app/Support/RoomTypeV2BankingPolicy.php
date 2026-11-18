<?php

namespace App\Support;

/** Verification is administrative; it never extends the inventory lease. */
final class RoomTypeV2BankingPolicy
{
    public static function describe(object $booking, ?string $outcome = null): array
    {
        $minutes = config('roomtype-v2.manual_verification_minutes', 15);
        if (!is_int($minutes) || $minutes < 15 || $minutes > 1440) {
            throw new \RuntimeException('Verification window must be an integer between 15 and 1440 minutes.');
        }
        $deadline = $booking->expires_at ? \Carbon\Carbon::parse($booking->expires_at) : null;
        $verification = $deadline?->copy()->addMinutes($minutes - 15);
        $holding = in_array($booking->status, ['pending', 'confirmed', 'completed'], true);
        $late = $booking->status !== 'pending' || !$deadline || $deadline->lte(now());
        return [
            'payment_deadline' => $deadline?->toIso8601String(),
            'verification_deadline' => $verification?->toIso8601String(),
            'inventory_reservation' => $holding ? 'holding' : 'released',
            'inventory_release_pending' => $booking->status === 'pending' && $late,
            'verification_state' => $outcome ?? ($late ? 'reconciliation_required' : 'awaiting_statement'),
            'confirmation_allowed' => !$late,
            'verification_window_elapsed' => !$verification || $verification->lte(now()),
            'hold_minutes' => 15,
            'warning' => $late ? 'Tiền được ghi nhận để đối soát; không tự khôi phục phòng đã giải phóng.' :
                'Thanh toán và xác minh trong 15 phút. Báo đã chuyển tiền không gia hạn giữ phòng.',
        ];
    }
}
