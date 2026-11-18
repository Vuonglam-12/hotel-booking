<?php

namespace App\Services;

use App\Support\LocalBookingDemo;
use Illuminate\Database\Connection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

final class LocalBookingReconciliationService
{
    /** Called under the same booking/payment transaction as the simulated notification. */
    public function record(Connection $db, object $booking, object $payment, string $reason, string $amount, ?string $notification = null): array
    {
        $key = hash('sha256', json_encode([$payment->id, $notification??('demo-'.$reason)], JSON_THROW_ON_ERROR));
        $existing = $db->table('booking_demo_reconciliation')->where('event_key', $key)->lockForUpdate()->first();
        $outcome=$reason==='late_payment'?'late_reconciliation':$reason.'_reconciliation';
        if ($existing) {
            abort_unless($existing->reason===$reason && $existing->received_amount===$amount,409,'Thông báo thanh toán lặp có nội dung khác với giao dịch đã ghi nhận.');
            return ['demo'=>true, 'duplicate'=>true, 'outcome'=>$outcome, 'reconciliation_id'=>(int)$existing->id];
        }
        $id = $db->table('booking_demo_reconciliation')->insertGetId([
            'booking_id'=>$booking->id, 'payment_id'=>$payment->id, 'event_key'=>$key, 'reason'=>$reason,
            'expected_amount'=>$booking->total_price, 'received_amount'=>$amount, 'state'=>'open',
        ]);
        return ['demo'=>true, 'duplicate'=>false, 'outcome'=>$outcome, 'reconciliation_id'=>$id];
    }

    public function listing(int $page = 1, ?string $state = null): array
    {
        $db = LocalBookingDemo::connection();
        $result = $db->table('booking_demo_reconciliation as r')->join('booking as b', 'b.id', '=', 'r.booking_id')
            ->join('payment as p', 'p.id', '=', 'r.payment_id')->join('hotel as h', 'h.id', '=', 'b.hotel_id')
            ->when($state, fn($q)=>$q->where('r.state', $state))->orderByDesc('r.id')
            ->select(['r.id','r.booking_id','r.payment_id','r.reason','r.expected_amount','r.received_amount','r.state','r.note',
                'r.evidence_reference','r.evidence_sha256','r.reviewed_by','r.reviewed_at','r.created_at','b.status as booking_status','p.transaction_id','h.name as hotel'])
            ->paginate(20, ['*'], 'page', $page)->toArray();
        foreach ($result['data'] as &$row) {
            $row = (array)$row;
            $row['reviews'] = $db->table('booking_demo_reconciliation_review')->where('reconciliation_id', $row['id'])
                ->orderBy('id')->get(['id','admin_id','state','note','evidence_reference','evidence_sha256','created_at']);
        }
        return $result;
    }

    public function review(int $id, int $admin, array $input, ?UploadedFile $evidence = null): array
    {
        $db = LocalBookingDemo::connection();
        abort_unless($db->table('customer')->where('id', $admin)->where('role', 'admin')->exists(), 403);
        $data = Validator::make($input + ['evidence'=>$evidence], [
            'state'=>'required|in:open,reviewed,refund_verified', 'note'=>'required|string|min:5|max:4000',
            'evidence_reference'=>'nullable|required_if:state,refund_verified|string|max:100',
            'evidence'=>'nullable|required_if:state,refund_verified|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ])->validate();
        $path = $evidence?->store('private/reconciliation-evidence', 'local');
        if ($evidence && (!is_string($path) || $path==='')) abort(503,'Chưa lưu được bằng chứng đối soát. Vui lòng thử lại.');
        $sha = $path ? hash_file('sha256', Storage::disk('local')->path($path)) : null;
        if ($path && (!is_string($sha) || !preg_match('/^[a-f0-9]{64}$/D',$sha))) {
            Storage::disk('local')->delete($path);
            abort(503,'Chưa kiểm chứng được tệp bằng chứng đối soát.');
        }
        try {
            return $db->transaction(function () use ($db, $id, $admin, $data, $path, $sha) {
                $entry = $db->table('booking_demo_reconciliation')->where('id', $id)->lockForUpdate()->first();
                abort_unless($entry, 404);
                $values = ['state'=>$data['state'], 'note'=>$data['note'], 'evidence_reference'=>$data['evidence_reference']??null,
                    'evidence_path'=>$path, 'evidence_sha256'=>$sha];
                $db->table('booking_demo_reconciliation_review')->insert($values + ['reconciliation_id'=>$id, 'admin_id'=>$admin]);
                $db->table('booking_demo_reconciliation')->where('id', $id)->update($values + ['reviewed_by'=>$admin, 'reviewed_at'=>now()]);
                // An administrative note never changes Payment, Booking, inventory or claims a gateway refund.
                return ['id'=>$id, 'state'=>$data['state'], 'message'=>'Đã lưu kết quả đối soát và lịch sử xử lý.'];
            }, 3);
        } catch (\Throwable $e) {
            if ($path) Storage::disk('local')->delete($path);
            throw $e;
        }
    }
}
