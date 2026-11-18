<?php

namespace App\Mail;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class BookingConfirmedV2 extends BookingConfirmed
{
    private bool $prepared=false;
    public function build()
    {
        if ($this->prepared) return $this;
        $this->booking->load(['hotel','customer','bookingRooms.roomType']);
        if (!in_array($this->booking->status,['confirmed','completed'],true)) throw new \RuntimeException('Released/unconfirmed Booking cannot send confirmation.');
        $mail=$this->subject('Xác nhận đặt phòng #'.$this->booking->id.' — Angel')->view('prepared-v2.email-booking-confirmed');
        $invoice=Invoice::with(['items','payment'])->where('booking_id',$this->booking->id)->firstOrFail();
        $mail->with(['invoice'=>$invoice]);
        $pdf=Pdf::loadView('prepared-v2.invoice-pdf',['invoice'=>$invoice,'booking'=>$this->booking])->setOptions(['defaultFont'=>'DejaVu Sans','isRemoteEnabled'=>false]);
        $mail->attachData($pdf->output(),'HoaDon_'.$invoice->invoice_no.'.pdf',['mime'=>'application/pdf']);
        $this->prepared=true; return $mail;
    }
}
