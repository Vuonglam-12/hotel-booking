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
        $invoices = Invoice::with('items')
            ->where('customer_id', auth('sanctum')->id())
            ->orderBy('issued_at', 'desc')
            ->paginate($request->per_page ?? 10);

        return response()->json($invoices);
    }

    // Hàm nội bộ — tạo invoice sau khi payment success
    // Gọi từ PaymentController sau khi VNPay callback thành công
    public static function createFromPayment(Booking $booking, Payment $payment): Invoice
    {
        // Tạo số hóa đơn: INV-20260321-0001
        $invoiceNo = 'INV-' . now()->format('Ymd') . '-' . str_pad($booking->id, 4, '0', STR_PAD_LEFT);

        // Load booking rooms để tính subtotal
        $booking->load('bookingRooms.roomType');
        $subtotal = 0;

        $invoice = DB::transaction(function () use ($booking, $payment, $invoiceNo, &$subtotal) {
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

            // Tạo invoice_item cho từng booking_room
            foreach ($booking->bookingRooms as $br) {
                $amount = $br->price_at_booking * $br->nights * $br->quantity;
                $subtotal += $amount;

                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'description' => $br->roomType->name . ' x' . $br->quantity . ' phòng x' . $br->nights . ' đêm'
                                    . ' (' . \Carbon\Carbon::parse($booking->check_in)->format('d/m/Y')
                                    . ' - ' . \Carbon\Carbon::parse($booking->check_out)->format('d/m/Y') . ')',
                    'quantity'    => $br->quantity,
                    'unit_price'  => $br->price_at_booking * $br->nights,
                    'amount'      => $amount,
                ]);
            }

            // Cập nhật subtotal
            $invoice->update(['subtotal' => $subtotal]);

            return $invoice;
        });

        return $invoice;
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

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'booking' => $booking,
        ]);

        // Lưu pdf_url vào DB
        $filename = 'invoice_' . $invoice->invoice_no . '.pdf';
        $invoice->update(['pdf_url' => $filename]);

        return $pdf->download($filename);
    }
}