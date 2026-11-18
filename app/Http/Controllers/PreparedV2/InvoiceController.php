<?php

namespace App\Http\Controllers\PreparedV2;

use App\Models\Booking;
use App\Models\Invoice;
use App\Support\RoomTypeV2Inventory;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends \App\Http\Controllers\InvoiceController
{
    /** GET/PDF must not mutate an invoice or bypass the financial writer. */
    public function exportPdf($booking_id)
    {
        RoomTypeV2Inventory::open('mysql');
        $booking=Booking::with(['hotel','customer','bookingRooms.roomType'])->where('customer_id',auth('sanctum')->id())->findOrFail($booking_id);
        $invoice=Invoice::with('items')->where('booking_id',$booking_id)->firstOrFail();
        return Pdf::loadView(config('roomtype-v2.final_integration',false)?'prepared-v2.invoice-pdf':'pdf.invoice',compact('booking','invoice'))->setOptions(['defaultFont'=>'DejaVu Sans','isRemoteEnabled'=>false])
            ->download('invoice_'.$invoice->invoice_no.'.pdf');
    }
}
