<?php

namespace App\Services\Invoice;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function getList(int $customerId, int $perPage = 10): LengthAwarePaginator
    {
        return Invoice::with('items')
            ->where('customer_id', $customerId)
            ->orderBy('issued_at', 'desc')
            ->paginate($perPage);
    }

    public function getDetail(int $bookingId, int $customerId): array
    {
        Booking::where('id', $bookingId)->where('customer_id', $customerId)->firstOrFail();

        $invoice = Invoice::with('items')->where('booking_id', $bookingId)->first();

        if (!$invoice) {
            throw new \Exception('Hóa đơn chưa được tạo — booking chưa thanh toán thành công');
        }

        return $this->formatInvoice($invoice);
    }

    public function exportPdf(int $bookingId, int $customerId): \Illuminate\Http\Response
    {
        $booking = Booking::where('id', $bookingId)->where('customer_id', $customerId)->firstOrFail();
        $invoice = Invoice::with('items')->where('booking_id', $bookingId)->first();

        if (!$invoice) {
            throw new \Exception('Hóa đơn chưa được tạo');
        }

        $booking->load(['hotel', 'customer', 'bookingRooms.roomType']);

        $pdf      = Pdf::loadView('pdf.invoice', ['invoice' => $invoice, 'booking' => $booking]);
        $filename = 'invoice_' . $invoice->invoice_no . '.pdf';
        $invoice->update(['pdf_url' => $filename]);

        return $pdf->download($filename);
    }

    public static function createFromPayment(Booking $booking, Payment $payment): Invoice
    {
        $booking->load('bookingRooms.roomType');

        return DB::transaction(function () use ($booking, $payment) {
            $todayPrefix = 'INV-' . now()->format('Ymd') . '-';

            $lastInvoice = Invoice::where('invoice_no', 'like', $todayPrefix . '%')
                ->lockForUpdate()
                ->orderBy('invoice_no', 'desc')
                ->first();

            $nextSeq   = $lastInvoice ? (int) substr($lastInvoice->invoice_no, -4) + 1 : 1;
            $invoiceNo = $todayPrefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

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
                'issued_at'     => now(),
            ]);

            $subtotal = 0;
            foreach ($booking->bookingRooms as $br) {
                $amount    = $br->price_at_booking * $br->nights * $br->quantity;
                $subtotal += $amount;

                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'description' => $br->roomType->name . ' x' . $br->quantity . ' phòng x' . $br->nights . ' đêm'
                                   . ' (' . Carbon::parse($booking->check_in)->format('d/m/Y')
                                   . ' - ' . Carbon::parse($booking->check_out)->format('d/m/Y') . ')',
                    'quantity'    => $br->quantity,
                    'unit_price'  => $br->price_at_booking * $br->nights,
                    'amount'      => $amount,
                ]);
            }

            $invoice->update(['subtotal' => $subtotal]);
            return $invoice;
        });
    }

    private function formatInvoice(Invoice $invoice): array
    {
        $data                           = $invoice->toArray();
        $data['subtotal_formatted']     = number_format($invoice->subtotal, 0, '.', ',') . ' VND';
        $data['total_formatted']        = number_format($invoice->total, 0, '.', ',') . ' VND';
        $data['discount_formatted']     = number_format($invoice->discount, 0, '.', ',') . ' VND';

        foreach ($data['items'] as &$item) {
            $item['unit_price_formatted'] = number_format($item['unit_price'], 0, '.', ',') . ' VND';
            $item['amount_formatted']     = number_format($item['amount'], 0, '.', ',') . ' VND';
        }

        return $data;
    }
}