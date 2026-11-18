<?php

namespace App\Services;

use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Support\LocalBookingDemo;
use App\Support\SmtpFailureDiagnostics;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Durable single-host delivery journal, outside cache/public; no catalog/payment schema changes. */
final class LocalBookingConfirmationMailService
{
    private function path(int $bookingId): string
    {
        if ($bookingId < 1) throw new \InvalidArgumentException('Invalid booking.');
        $root = rtrim((string) config('booking-demo.confirmation_mail_path'), '/\\');
        $allowed = realpath(storage_path('app/private'));
        $parent = realpath(dirname($root));
        if (!$allowed || !$parent || ($parent !== $allowed && !str_starts_with($parent, $allowed.DIRECTORY_SEPARATOR)) || is_link($root)) {
            throw new \RuntimeException('Private mail journal path rejected.');
        }
        $db = config('database.connections.mysql');
        $key = hash('sha256', implode('|', [$db['host'], $db['port'], $db['database'], $bookingId]));
        return $root.DIRECTORY_SEPARATOR.$key.'.json';
    }

    private function read(string $path): array
    {
        if (!is_file($path)) return ['status'=>'not_sent'];
        $record = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($record) || !in_array($record['status'] ?? '', ['sending','sent','uncertain','preparation_failed'], true)) {
            throw new \RuntimeException('Mail journal requires inspection.');
        }
        return $record;
    }

    private function write(string $path, array $record): void
    {
        $temporary = tempnam(dirname($path), '.mail-');
        if (!$temporary) throw new \RuntimeException('Cannot prepare mail journal.');
        try {
            chmod($temporary, 0600);
            $handle = fopen($temporary, 'wb');
            if (!$handle) throw new \RuntimeException('Cannot open mail journal.');
            try {
                $json = json_encode($record, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                if (fwrite($handle, $json) !== strlen($json) || !fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
                    throw new \RuntimeException('Cannot persist mail journal.');
                }
            } finally { fclose($handle); }
            if (!rename($temporary, $path)) throw new \RuntimeException('Cannot commit mail journal.');
        } finally { if (is_file($temporary)) unlink($temporary); }
    }

    public function status(int $bookingId): array
    {
        if (config('booking-demo.confirmation_mail_enabled') !== true) return ['status'=>'disabled'];
        try {
            $record = $this->read($this->path($bookingId));
            return array_intersect_key($record, array_flip(['status','sent_at','smtp_stage','smtp_code']));
        } catch (\Throwable) { return ['status'=>'uncertain']; }
    }

    /** SMTP ambiguity is never retried automatically; booking/payment remain committed. */
    public function deliver(int $bookingId): array
    {
        if (config('booking-demo.confirmation_mail_enabled') !== true) return ['status'=>'disabled'];
        $lock = null;
        $record = null;
        $path = null;
        $transportStarted = false;
        try {
            $db = LocalBookingDemo::connection();
            if ($db->transactionLevel() !== 0) throw new \RuntimeException('Mail must run after commit.');
            $path = $this->path($bookingId);
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) throw new \RuntimeException('Cannot create mail journal.');
            $lock = fopen($path.'.lock', 'c');
            if (!$lock) throw new \RuntimeException('Cannot open mail lock.');
            chmod($path.'.lock', 0600);
            if (!flock($lock, LOCK_EX | LOCK_NB)) return ['status'=>'sending','duplicate'=>true];
            $record = $this->read($path);
            $transportStarted = in_array($record['status'], ['sent','sending','uncertain'], true);
            if (in_array($record['status'], ['sent','uncertain','sending'], true)) {
                if ($record['status']==='sending') {
                    $record['status']='uncertain';
                    $this->write($path, $record);
                }
                return ['status'=>$record['status'],'duplicate'=>true];
            }
            if (($record['attempts'] ?? 0) >= 3) return ['status'=>'preparation_failed','duplicate'=>true];

            $marker = $db->table('booking_demo')->where('booking_id', $bookingId)->first();
            $booking = Booking::with(['hotel','customer','bookingRooms.roomType','bookingRooms.room','invoice.items','invoice.payment'])->findOrFail($bookingId);
            $invoice = $booking->invoice;
            $payment = $invoice?->payment;
            if (!$marker || !in_array($booking->status, ['confirmed','completed'], true) || !$payment || $payment->payment_status!=='success'
                || (int)$payment->booking_id!==$bookingId || (int)$invoice->customer_id!==(int)$booking->customer_id
                || (string)$payment->getRawOriginal('amount')!==(string)$booking->getRawOriginal('total_price')
                || (string)$invoice->getRawOriginal('total')!==(string)$booking->getRawOriginal('total_price')
                || !filter_var($booking->customer?->email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('A paid booking and matching invoice are required.');
            }
            $record = ['status'=>'preparation_failed','booking_id'=>$bookingId,'payment_id'=>(int)$payment->id,
                'recipient_sha256'=>hash('sha256', mb_strtolower($booking->customer->email)),
                'attempts'=>($record['attempts'] ?? 0)+1,'updated_at'=>now()->toIso8601String()];
            $mail = (new BookingConfirmed($booking, $marker->room_name))->to($booking->customer->email);
            $mail->withSymfonyMessage(static function ($message) use ($path) {
                $message->getHeaders()->addIdHeader('Message-ID', basename($path, '.json').'@aurel.local');
            });
            $mail->render(); // Prepare template/PDF before marking an external transport attempt.
            $record['status']='sending';
            $this->write($path, $record);
            $transportStarted = true;
            $mailer = (string) config('mail.default');
            if (!app()->environment('testing')) config(['mail.mailers.'.$mailer.'.timeout'=>15]);
            $sent = Mail::mailer($mailer)->send($mail);
            if (!$sent) throw new \RuntimeException('Mail transport did not acknowledge the message.');
            $record['status']='sent';
            $record['sent_at']=now()->toIso8601String();
            $this->write($path, $record);
            return ['status'=>'sent','duplicate'=>false];
        } catch (\Throwable $error) {
            $diagnostic = SmtpFailureDiagnostics::from($error);
            $definitelyBeforeData = in_array($diagnostic['stage'], ['connection','starttls','authentication'], true);
            $state = $transportStarted && !$definitelyBeforeData ? 'uncertain' : 'preparation_failed';
            if ($record !== null && $path !== null) {
                $record['status']=$state;
                $record['smtp_stage']=$diagnostic['stage'];
                $record['smtp_code']=$diagnostic['smtp_code'];
                $record['updated_at']=now()->toIso8601String();
                try { $this->write($path, $record); } catch (\Throwable) {}
            }
            Log::warning('Booking confirmation email not acknowledged.', ['booking_id'=>$bookingId,'state'=>$state,'exception_class'=>get_class($error)]);
            return ['status'=>$state,'smtp_stage'=>$diagnostic['stage'],'smtp_code'=>$diagnostic['smtp_code']];
        } finally {
            if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
        }
    }
}
