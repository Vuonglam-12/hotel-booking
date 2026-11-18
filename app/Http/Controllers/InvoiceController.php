<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    // GET /api/invoices/{booking_id}
    // Xem hóa đơn của 1 booking
    public function show($booking_id)
    {
        $customerId = auth('sanctum')->id();

        $booking = Booking::where('id', $booking_id)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $invoice = Invoice::with('items')
            ->where('booking_id', $booking_id)
            ->first();

        if (!$invoice) {
            return response()->json([
                'message' => 'Hóa đơn chưa được tạo — booking chưa thanh toán thành công',
            ], 404);
        }

        return response()->json([
            'invoice' => $this->formatInvoice($invoice),
        ]);
    }

    // GET /api/invoices
    // Danh sách hóa đơn của user
    public function index(Request $request)
    {
        $data = $request->validate(['per_page' => 'sometimes|integer|between:1,100']);
        $invoices = Invoice::with('items')
            ->where('customer_id', auth('sanctum')->id())
            ->orderBy('issued_at', 'desc')
            ->paginate($data['per_page'] ?? 10);

        return response()->json($invoices);
    }

    // Hàm nội bộ — tạo invoice sau khi payment success
    // Gọi từ PaymentController sau khi VNPay callback thành công
    public static function createFromPayment(Booking $booking, Payment $payment, ?string $demoRoomName = null): Invoice
    {
        $booking->load('bookingRooms.roomType');

        return DB::transaction(function () use ($booking, $payment, $demoRoomName) {
            $demo = $demoRoomName !== null && $payment->payment_method === 'demo' && str_starts_with($payment->transaction_id, 'DEMO_LOCAL_');
            if ($demo && ($existing = Invoice::where('booking_id', $booking->id)->lockForUpdate()->first())) return $existing;
            // Đếm số invoice trong ngày hôm nay, lock để tránh race condition
            $todayPrefix = 'INV-' . now()->format('Ymd') . '-';
            
            $lastInvoice = $demo ? null : Invoice::where('invoice_no', 'like', $todayPrefix . '%')
                ->lockForUpdate()
                ->orderBy('invoice_no', 'desc')
                ->first();

            $nextSeq = $lastInvoice
                ? (int) substr($lastInvoice->invoice_no, -4) + 1
                : 1;

            $invoiceNo = $todayPrefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
            if ($demo) $invoiceNo = 'DEMO-INV-' . $booking->id;

            // Phần còn lại giữ nguyên
            $subtotal = 0;
            $nightlySubtotalCents = 0;
            $hasNightlySnapshot = false;

            $invoice = Invoice::create([
                'invoice_no'    => $invoiceNo,
                'booking_id'    => $booking->id,
                'payment_id'    => $payment->id,
                'customer_id'   => $booking->customer_id,
                'subtotal'      => 0,
                'service_total' => 0,
                'discount'      => 0,
                'tax'           => 0,
                'total'         => $booking->total_price,
                'issued_at'     => $demo ? DB::selectOne('SELECT CURRENT_TIMESTAMP n')->n : now(),
                'notes'         => $demo ? 'DEMO — Hóa đơn mô phỏng, không có chuyển tiền thật.' : null,
            ]);

            foreach ($booking->bookingRooms as $br) {
                $nightly = $demo ? app(\App\Services\RoomPricingService::class)->snapshot((int)$br->id) : [];
                if ($nightly) {
                    abort_unless(count($nightly)===(int)$br->nights,409,'Thiếu chi tiết giá từng đêm của đơn đặt phòng.');
                    $hasNightlySnapshot = true;
                    foreach ($nightly as $night) {
                        $cents = (int)str_replace('.', '', $night['unit_price']);
                        $amountCents = $cents * (int)$br->quantity;
                        $nightlySubtotalCents += $amountCents;
                        $amount = intdiv($amountCents,100).'.'.str_pad((string)($amountCents%100),2,'0',STR_PAD_LEFT);
                        InvoiceItem::create([
                            'invoice_id'=>$invoice->id,
                            'description'=>$demoRoomName.' x'.$br->quantity.' phòng · Đêm '.\Carbon\Carbon::parse($night['date'])->format('d/m/Y')
                                .($night['base_price']!==null&&$night['surcharge']!==null?' · Giá cơ bản '.number_format((float)$night['base_price'],2,',','.').' ₫ + phụ thu '.number_format((float)$night['surcharge'],2,',','.').' ₫':''),
                            'quantity'=>$br->quantity, 'unit_price'=>$night['unit_price'], 'amount'=>$amount,
                        ]);
                    }
                    continue;
                }
                $amount = $br->price_at_booking * $br->nights * $br->quantity;
                $subtotal += $amount;

                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'description' => ($demo ? $demoRoomName : $br->roomType->name) . ' x' . $br->quantity . ' phòng x' . $br->nights . ' đêm'
                                    . ' (' . \Carbon\Carbon::parse($booking->check_in)->format('d/m/Y')
                                    . ' - ' . \Carbon\Carbon::parse($booking->check_out)->format('d/m/Y') . ')',
                    'quantity'    => $br->quantity,
                    'unit_price'  => $br->price_at_booking * $br->nights,
                    'amount'      => $amount,
                ]);
            }

            if ($hasNightlySnapshot) {
                abort_unless($subtotal===0 && $nightlySubtotalCents===(int)str_replace('.', '', (string)$booking->total_price),409,'Tổng giá từng đêm không khớp đơn đặt phòng.');
                $subtotal=intdiv($nightlySubtotalCents,100).'.'.str_pad((string)($nightlySubtotalCents%100),2,'0',STR_PAD_LEFT);
            }
            $invoice->update(['subtotal' => $subtotal]);

            return $invoice;
        });
    }

    // Format giá cho response
    private function formatInvoice(Invoice $invoice): array
    {
        $data = $invoice->toArray();
        $data['subtotal_formatted']      = number_format($invoice->subtotal, 0, '.', ',') . ' VND';
        $data['total_formatted']         = number_format($invoice->total, 0, '.', ',') . ' VND';
        $data['discount_formatted']      = number_format($invoice->discount, 0, '.', ',') . ' VND';

        foreach ($data['items'] as &$item) {
            $item['unit_price_formatted'] = number_format($item['unit_price'], 0, '.', ',') . ' VND';
            $item['amount_formatted']     = number_format($item['amount'], 0, '.', ',') . ' VND';
        }

        return $data;
    }

    // GET /api/invoices/{booking_id}/pdf
    public function exportPdf($booking_id)
    {
        $customerId = auth('sanctum')->id();

        $booking = Booking::where('id', $booking_id)
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $invoice = Invoice::with('items')
            ->where('booking_id', $booking_id)
            ->first();

        if (!$invoice) {
            return response()->json([
                'message' => 'Hóa đơn chưa được tạo'
            ], 404);
        }

        $booking->load(['hotel', 'customer', 'bookingRooms.roomType']);
        if (str_starts_with($invoice->invoice_no,'DEMO-INV-') && DB::getSchemaBuilder()->hasTable('booking_demo')) {
            $name = DB::table('booking_demo')->where('booking_id',$booking->id)->value('room_name');
            if ($name) $booking->bookingRooms->each(function($line)use($name){$type=clone $line->roomType;$type->name=$name;$line->setRelation('roomType',$type);});
        }

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'booking' => $booking,
        ]);

        // Download the existing invoice without changing persisted data.
        $filename = str_starts_with($invoice->invoice_no,'DEMO-')
            ? 'AUREL-hoa-don-' . $booking->id . '.pdf'
            : 'invoice_' . $invoice->invoice_no . '.pdf';

        return $pdf->download($filename);
    }
}
