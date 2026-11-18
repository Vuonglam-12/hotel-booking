<?php

namespace App\Services\PreparedV2;

use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Support\RoomTypeV2Database;
use Illuminate\Support\Facades\Mail;

/** After-commit Blade/PDF pipeline: memory transport or explicitly approved staging SMTP recipients. */
final class BookingMailSender
{
    public function __invoke(array $event): void
    {
        $db = RoomTypeV2Database::connection('mysql');
        if ($db->transactionLevel() !== 0) throw new \RuntimeException('Mail must run after commit.');
        $mailer='array';
        if (config('roomtype-v2.production_manifest')!==null && config('roomtype-v2.staging_mail')===null) throw new \App\Support\BookingDeliveryPreSendFailure('Production transport requires separate approved recipient/SMTP configuration.');
        if (config('roomtype-v2.staging_mail')!==null) {
            try { $settings=\App\Support\RoomTypeV2PaymentConfiguration::smtp(); }
            catch (\Throwable $e) { throw new \App\Support\BookingDeliveryPreSendFailure('Staging transport is not approved.'); }
            $mailer='roomtype_v2_staging';
            config(['mail.mailers.'.$mailer=>array_merge($settings,['transport'=>'smtp','scheme'=>($settings['encryption']??null)==='ssl'?'smtps':'smtp','timeout'=>10]),'mail.from'=>['address'=>$settings['from_address'],'name'=>$settings['from_name']??'AUREL staging']]);
        } elseif (config('mail.mailers.array.transport') !== 'array') throw new \App\Support\BookingDeliveryPreSendFailure('Only the isolated array mail transport is allowed.');
        $booking = Booking::with('customer')->findOrFail($event['booking_id']);
        if ($mailer==='array' && !str_ends_with($booking->customer->email, '@example.test')) throw new \App\Support\BookingDeliveryPreSendFailure('Only synthetic test recipients are allowed.');
        if ($mailer!=='array') {
            try { \App\Support\RoomTypeV2PaymentConfiguration::smtp($booking->customer->email); }
            catch (\Throwable $e) { throw new \App\Support\BookingDeliveryPreSendFailure('Recipient is not approved.'); }
        }
        $messageId = hash('sha256', $event['event_key']).'@roomtype-v2-test.invalid';
        $mailable=config('roomtype-v2.final_integration',false)?new \App\Mail\BookingConfirmedV2($booking):new BookingConfirmed($booking);
        $mail = $mailable->withSymfonyMessage(function ($message) use ($messageId) {
            $message->getHeaders()->addIdHeader('Message-ID', $messageId);
        });
        if (config('roomtype-v2.delivery_safety',false)) {
            try { $mail->render(); }
            catch (\Throwable $e) { throw new \App\Support\BookingDeliveryPreSendFailure('Template/PDF preparation failed.'); }
        }
        $result=Mail::mailer($mailer)->to($booking->customer->email)->send($mail);
        if (!$result) throw new \RuntimeException('Transport did not acknowledge the message.');
    }
}
