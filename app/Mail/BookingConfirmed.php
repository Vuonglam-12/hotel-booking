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
    public ?string $roomNameSnapshot;
    private bool $built = false;

    public function __construct(Booking $booking, ?string $roomNameSnapshot = null)
    {
        $this->booking = $booking;
        $this->roomNameSnapshot = $roomNameSnapshot;
    }

    public function build()
    {
        if ($this->built) return $this;
        // Load đủ relations cần thiết cho blade email + PDF
        $this->booking->loadMissing(['hotel', 'customer', 'bookingRooms.roomType', 'bookingRooms.room']);

        $mail = $this
            ->subject('Xác nhận đặt phòng #' . $this->booking->id . ' — Angel')
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

            $filename = str_starts_with($invoice->invoice_no, 'DEMO-INV-')
                ? 'AUREL-Hoa-don-'.str_pad((string)$this->booking->id, 6, '0', STR_PAD_LEFT).'.pdf'
                : 'HoaDon_' . $invoice->invoice_no . '.pdf';

            // attachData: attach trực tiếp từ binary, không cần lưu file
            $mail->attachData(
                $pdf->output(),
                $filename,
                ['mime' => 'application/pdf']
            );
        }

        $this->built = true;
        return $mail;
    }
}
