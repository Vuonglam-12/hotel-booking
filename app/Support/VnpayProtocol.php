<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/** VNPAY PAY v2.1.0. Pure protocol code: never calls a gateway or reads application credentials. */
final class VnpayProtocol
{
    public function __construct(private readonly array $settings)
    {
        if (!preg_match('/^[A-Za-z0-9]{8}$/D', $settings['tmn_code'] ?? '') || strlen($settings['hash_secret'] ?? '') < 16) {
            throw new \RuntimeException('Missing verified VNPAY settings; no payment URL or success is fabricated.');
        }
    }

    public function sign(array $fields): string
    {
        unset($fields['vnp_SecureHash'], $fields['vnp_SecureHashType']); ksort($fields, SORT_STRING);
        return hash_hmac('sha512', http_build_query($fields, '', '&', PHP_QUERY_RFC1738), $this->settings['hash_secret']);
    }

    public function verify(array $query): array
    {
        $fields=[];
        foreach ($query as $key=>$value) if (str_starts_with($key,'vnp_')) {
            if (!is_string($value) || strlen($value)>1000) throw ValidationException::withMessages(['signature'=>'Invalid callback parameters.']);
            $fields[$key]=$value;
        }
        $signature=$fields['vnp_SecureHash'] ?? '';
        if (!preg_match('/^[a-fA-F0-9]{128}$/D',$signature) || !hash_equals($this->sign($fields),strtolower($signature))) throw ValidationException::withMessages(['signature'=>'Invalid VNPAY signature.']);
        unset($fields['vnp_SecureHash'],$fields['vnp_SecureHashType']);
        foreach (['vnp_TxnRef'=>'/^[A-Za-z0-9_]{1,100}$/D','vnp_Amount'=>'/^\d{1,12}$/D','vnp_ResponseCode'=>'/^\d{2}$/D',
            'vnp_TransactionStatus'=>'/^\d{2}$/D','vnp_TransactionNo'=>'/^\d{1,20}$/D','vnp_PayDate'=>'/^\d{14}$/D'] as $key=>$rule) {
            if (!preg_match($rule,$fields[$key] ?? '')) throw ValidationException::withMessages(['callback'=>'Missing/invalid '.$key]);
        }
        if (($fields['vnp_TmnCode'] ?? '') !== $this->settings['tmn_code']) throw ValidationException::withMessages(['signature'=>'Merchant mismatch.']);
        $stamp=\DateTimeImmutable::createFromFormat('!YmdHis',$fields['vnp_PayDate'],new \DateTimeZone('Asia/Ho_Chi_Minh'));
        if (!$stamp || $stamp->format('YmdHis') !== $fields['vnp_PayDate']) throw ValidationException::withMessages(['callback'=>'Invalid payment date.']);
        if ($fields['vnp_ResponseCode']==='00' && $fields['vnp_TransactionStatus']==='00' && $fields['vnp_TransactionNo']==='0') throw ValidationException::withMessages(['callback'=>'Success requires a gateway transaction reference.']);
        ksort($fields,SORT_STRING); return $fields;
    }

    public function assertTarget(string $ip): void
    {
        $url=$this->settings['url'] ?? ''; $return=$this->settings['return_url'] ?? '';
        // This preparation module supports sandbox only. Production config is a separate approval gate.
        if ($url !== 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html' || !filter_var($return,FILTER_VALIDATE_URL)
            || !filter_var($ip,FILTER_VALIDATE_IP)) throw new \RuntimeException('Only an explicitly configured sandbox URL is supported.');
    }

    public function url(object $booking, object $payment, string $ip): string
    {
        $this->assertTarget($ip); $url=$this->settings['url']; $return=$this->settings['return_url'];
        $fields=['vnp_Version'=>'2.1.0','vnp_Command'=>'pay','vnp_TmnCode'=>$this->settings['tmn_code'],
            'vnp_Amount'=>(string) RoomTypeV2Inventory::cents($payment->amount),'vnp_CurrCode'=>'VND',
            'vnp_TxnRef'=>$payment->transaction_id,'vnp_OrderInfo'=>'Thanh toan booking '.$booking->id,'vnp_OrderType'=>'other',
            'vnp_Locale'=>'vn','vnp_IpAddr'=>$ip,'vnp_ReturnUrl'=>$return,
            'vnp_CreateDate'=>now()->timezone('Asia/Ho_Chi_Minh')->format('YmdHis'),
            'vnp_ExpireDate'=>Carbon::parse($booking->expires_at,config('app.timezone'))->timezone('Asia/Ho_Chi_Minh')->format('YmdHis')];
        ksort($fields,SORT_STRING); $fields['vnp_SecureHash']=$this->sign($fields);
        return $url.'?'.http_build_query($fields,'','&',PHP_QUERY_RFC1738);
    }
}
