<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class BookingConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    public function build()
    {
        // Load đủ relations cần thiết cho blade email + PDF
        $this->booking->load(['hotel', 'customer', 'bookingRooms.roomType']);

        $mail = $this
            ->subject('Xác nhận đặt phòng #' . $this->booking->id . ' — Hotel Booking')
            ->view('emails.booking_confirmed');

        // Attach PDF invoice nếu đã có invoice trong DB
        $invoice = Invoice::with('items')
            ->where('booking_id', $this->booking->id)
            ->first();

        if ($invoice) {
            $pdf = Pdf::loadView('pdf.invoice', [
                'invoice' => $invoice,
                'booking' => $this->booking,
            ])->setOptions([
                'defaultFont'          => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
            ]);

            $filename = 'HoaDon_' . $invoice->invoice_no . '.pdf';

            // attachData: attach trực tiếp từ binary, không cần lưu file
            $mail->attachData(
                $pdf->output(),
                $filename,
                ['mime' => 'application/pdf']
            );
        }

        return $mail;
    }
}