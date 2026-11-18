<?php

// Render-only verification against an existing owned synthetic dataset. No send or financial writer.
use App\Mail\BookingConfirmedV2;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\BookingDocumentPresentation;
use App\Support\RoomTypeV2Inventory;
use App\Support\RoomTypeV2TestDatabase;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;

$root = dirname(__DIR__, 2);
$allowed = realpath($root.'/storage/app/private/roomtype-v2-tests');
$path = realpath($argv[1] ?? '');
if (! $path || ! $allowed || ! str_starts_with($path, $allowed.DIRECTORY_SEPARATOR)
    || basename($path) !== 'sepay-staging.json') {
    throw new RuntimeException('An existing owned synthetic SePay descriptor is required.');
}
$stage = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
if (($stage['synthetic_only'] ?? false) !== true || realpath($stage['runtime']) !== dirname($path)
    || realpath($stage['settings_file']) !== dirname($path).DIRECTORY_SEPARATOR.'test-environment.json') {
    throw new RuntimeException('Synthetic descriptor ownership failed.');
}
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.$stage['settings_file']);
require __DIR__.'/roomtype_v2_test_bootstrap.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
    exit(1);
});
$db = RoomTypeV2TestDatabase::connection('mysql');
if ($db->getDatabaseName() !== $stage['database']) {
    throw new RuntimeException('Descriptor database differs from the isolated connection.');
}
$db->statement('SET SESSION TRANSACTION READ ONLY');
$db->beginTransaction();
Mail::fake();
config(['aurel_email_test.warning' => true]);
$output = $root.'/storage/app/private/aurel-document-preview';
if (! is_dir($output)) {
    mkdir($output, 0700, true);
}
$checks = [];
$check = static function (string $name, bool $passed) use (&$checks): void {
    if (! $passed) {
        throw new RuntimeException('FAIL: '.$name);
    }
    $checks[] = $name;
};
$snapshot = static function () use ($db): string {
    $data = [];
    foreach (['booking', 'payment', 'invoice', 'invoice_item', 'booking_effect', 'booking_delivery_audit'] as $table) {
        $data[$table] = $db->table($table)->orderBy('id')->get()->all();
    }

    return hash('sha256', serialize($data));
};

try {
    $before = $snapshot();
    $booking = Booking::with(['customer', 'hotel', 'bookingRooms.roomType', 'payment'])->findOrFail($stage['booking_id']);
    $invoice = Invoice::with(['items', 'payment'])->where('booking_id', $booking->id)->sole();
    $check('Existing synthetic Booking and Payment are confirmed and successful',
        $booking->status === 'confirmed' && $invoice->payment?->payment_status === 'success'
        && (int) $invoice->payment->id === (int) $stage['payment_id']);
    $cents = static fn ($value) => RoomTypeV2Inventory::cents($value ?? '0.00');
    $check('Invoice total equals subtotal + services - discount + tax',
        $cents($invoice->total) === $cents($invoice->subtotal) + $cents($invoice->service_total) - $cents($invoice->discount) + $cents($invoice->tax));
    $check('Invoice item amounts equal subtotal + services',
        $invoice->items->sum(fn ($item) => $cents($item->amount)) === $cents($invoice->subtotal) + $cents($invoice->service_total));
    $check('Each stored item amount equals quantity x unit price', $invoice->items->every(
        fn ($item) => $cents($item->amount) === $cents($item->unit_price) * $item->quantity));
    $check('Invoice, Payment and existing descriptor have the same total',
        $cents($invoice->total) === $cents($invoice->payment->amount) && $cents($invoice->total) === (int) $stage['amount_vnd'] * 100);

    $localLogo = 'file:///'.str_replace('\\', '/', public_path('image/logothuonghieu.png'));
    $saveHtml = static function (string $name, string $html) use ($output, $localLogo): void {
        // Only the local preview gets a file URI. The actual email keeps its public asset URL.
        $html = str_replace([asset('image/logothuonghieu.png'), public_path('image/logothuonghieu.png')], $localLogo, $html);
        file_put_contents($output.'/'.$name.'.html', $html);
    };
    $render = static fn ($view, $data) => view('prepared-v2.'.$view, $data)->render();
    $data = compact('booking', 'invoice');
    $mailable = new BookingConfirmedV2($booking);
    $email = $mailable->render();
    $pdfHtml = $render('invoice-pdf', $data);
    $saveHtml('email', $email);
    $saveHtml('invoice', $pdfHtml);
    $check('Mailable attaches exactly one PDF of the existing invoice',
        count($mailable->rawAttachments) === 1
        && $mailable->rawAttachments[0]['name'] === 'HoaDon_'.$invoice->invoice_no.'.pdf'
        && str_starts_with($mailable->rawAttachments[0]['data'], '%PDF-'));
    file_put_contents($output.'/invoice.pdf', $mailable->rawAttachments[0]['data']);
    $check('Staging warning is present in both views', str_contains($email, 'THỬ NGHIỆM — KHÔNG CÓ GIÁ TRỊ THANH TOÁN')
        && str_contains($pdfHtml, 'THỬ NGHIỆM — KHÔNG CÓ GIÁ TRỊ THANH TOÁN'));
    $expectedTotal = BookingDocumentPresentation::money($invoice->total);
    $check('Both documents display the stored invoice total', str_contains($email, $expectedTotal) && str_contains($pdfHtml, $expectedTotal));
    $check('No legacy brand or invented support address', ! preg_match('/holidayviet/i', $email.$pdfHtml));
    $check('Missing detail URL hides the CTA', ! str_contains($email, 'Xem chi tiết đặt phòng'));
    $check('Gmail message body remains below clipping threshold', strlen($email) < 102400);
    foreach (['', '/bookings', 'javascript:alert(1)', 'data:text/html,test', 'https://', 'https://user:secret@example.test/bookings/1'] as $url) {
        $html = $render('email-booking-confirmed', $data + ['bookingDetailsUrl' => $url]);
        $check('Unsafe/missing URL hides CTA: '.$url, ! str_contains($html, 'Xem chi tiết đặt phòng'));
    }
    $withUrl = $render('email-booking-confirmed', $data + ['bookingDetailsUrl' => 'https://aurel.example.test/bookings/1?tab=invoice&view=detail']);
    $check('Valid HTTPS URL shows an escaped CTA', str_contains($withUrl, 'Xem chi tiết đặt phòng') && str_contains($withUrl, '&amp;view=detail'));
    $saveHtml('email-with-url', $withUrl);

    // Variants change only in-memory model attributes/relations; never save them.
    foreach (['pending', 'failed', 'expired', 'refunded', null] as $status) {
        $variant = clone $invoice;
        $payment = $status === null ? null : clone $invoice->payment;
        if ($payment) {
            $payment->payment_status = $status;
        }
        $variant->setRelation('payment', $payment);
        foreach (['invoice-pdf', 'email-booking-confirmed'] as $view) {
            $html = $render($view, ['booking' => $booking, 'invoice' => $variant]);
            $check($view.' does not claim success for '.($status ?? 'missing payment'),
                ! str_contains($html, 'Đã thanh toán') && ! str_contains($html, 'Thanh toán thành công'));
        }
    }
    config(['aurel_email_test.warning' => false]);
    foreach (['invoice-pdf', 'email-booking-confirmed'] as $view) {
        $check('Live rendering has no test warning: '.$view,
            ! str_contains($render($view, $data), 'THỬ NGHIỆM — KHÔNG CÓ GIÁ TRỊ THANH TOÁN'));
    }
    config(['aurel_email_test.warning' => true]);
    $missingBooking = clone $booking;
    foreach (['customer', 'hotel', 'payment'] as $relation) {
        $missingBooking->setRelation($relation, null);
    }
    $missingBooking->setRelation('bookingRooms', new Illuminate\Database\Eloquent\Collection);
    $missingBooking->check_in = null;
    $missingBooking->check_out = null;
    $missingBooking->num_guests = null;
    $missingInvoice = clone $invoice;
    $missingInvoice->issued_at = null;
    $missingInvoice->total = null;
    $missingInvoice->subtotal = null;
    $missingInvoice->service_total = null;
    $missingInvoice->discount = null;
    $missingInvoice->tax = null;
    $missingInvoice->setRelation('payment', null);
    $missingInvoice->setRelation('items', new Illuminate\Database\Eloquent\Collection);
    foreach (['invoice-pdf', 'email-booking-confirmed'] as $view) {
        $html = $render($view, ['booking' => $missingBooking, 'invoice' => $missingInvoice]);
        $check('Missing data renders explicitly without invented dates/amounts: '.$view,
            str_contains($html, 'Chưa có thông tin') && ! str_contains($html, $expectedTotal)
            && ! str_contains($html, now()->format('d/m/Y')) && ! str_contains($html, 'mailto:'));
    }

    $longBooking = clone $booking;
    $customer = clone $booking->customer;
    $customer->name = 'Nguyễn Thị Ánh — dữ liệu tổng hợp kiểm tra tiếng Việt';
    $customer->email = str_repeat('a', 64).'@synthetic.example.test';
    $hotel = clone $booking->hotel;
    $hotel->name = str_repeat('Khách sạn thử nghiệm tiếng Việt — ', 4);
    $hotel->address = str_repeat('Đường Nguyễn Huệ, phường Bến Nghé, Thành phố Hồ Chí Minh. ', 3);
    $longBooking->setRelation('customer', $customer)->setRelation('hotel', $hotel);
    $longBooking->special_request = str_repeat('Yêu cầu synthetic có dấu tiếng Việt. ', 8).'<script>alert(1)</script>';
    $longInvoice = clone $invoice;
    $items = [];
    for ($index = 1; $index <= 70; $index++) {
        $items[] = new InvoiceItem(['description' => 'Dịch vụ tổng hợp '.$index.' — Phòng nghỉ hướng biển, kiểm tra tiếng Việt',
            'quantity' => 2, 'unit_price' => '100000.00', 'amount' => '200000.00']);
    }
    $longInvoice->setRelation('items', new Illuminate\Database\Eloquent\Collection($items));
    $longInvoice->subtotal = '14000000.00';
    $longInvoice->service_total = '0.00';
    $longInvoice->discount = '100000.00';
    $longInvoice->tax = '200000.00';
    $longInvoice->total = '14100000.00';
    $longData = ['booking' => $longBooking, 'invoice' => $longInvoice];
    $longEmail = $render('email-booking-confirmed', $longData);
    $check('Dynamic content is HTML escaped', ! str_contains($longEmail, '<script>') && str_contains($longEmail, '&lt;script&gt;'));
    $saveHtml('email-long', $longEmail);
    $longPdf = Pdf::loadView('prepared-v2.invoice-pdf', $longData)->setOptions(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]);
    file_put_contents($output.'/invoice-long.pdf', $longPdf->output());
    $check('Long Vietnamese invoice spans multiple A4 pages', $longPdf->getDomPDF()->getCanvas()->get_page_count() > 1);
    Mail::assertNothingSent();
    $check('Database snapshots are unchanged after rendering and no mail was sent', $snapshot() === $before);
    $report = ['synthetic_only' => true, 'mail_sent' => false, 'database_writes' => false,
        'source_descriptor' => $path, 'invoice_no' => $invoice->invoice_no,
        'total_vnd' => $invoice->total, 'checks_passed' => count($checks), 'checks' => $checks,
        'long_pdf_pages' => $longPdf->getDomPDF()->getCanvas()->get_page_count()];
    file_put_contents($output.'/render-results.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    echo json_encode(['checks_passed' => count($checks), 'output' => $output, 'mail_sent' => false, 'database_writes' => false], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $db->rollBack();
    $db->disconnect();
}
