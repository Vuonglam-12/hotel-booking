<?php

namespace App\Support;

/** Validates readiness without contacting banks, gateways or mail providers; never returns secrets. */
final class RoomTypeV2PaymentConfiguration
{
    public static function banking(): ?array
    {
        $b=config('roomtype-v2.banking_instructions');
        if (!is_array($b) || ($b['verified']??false)!==true) return null;
        foreach (['bank_name','bank_code','account_name','instructions'] as $key) {
            if (!is_string($b[$key]??null) || trim($b[$key])==='' || strlen($b[$key])>1000) return null;
        }
        if (!preg_match('/^[0-9]{4,30}$/D',$b['account_number']??'') || ($b['transfer_reference']??null)!=='payment.transaction_id') return null;
        $image=AccommodationImage::localUrl($b['qr_image_url']??null);
        $payload=$b['qr_payload']??null;
        if (!$image && (!is_string($payload) || $payload==='' || strlen($payload)>2048)) return null;
        return array_intersect_key(array_merge($b,['qr_image_url'=>$image]),array_flip([
            'bank_name','bank_code','account_name','account_number','instructions','qr_image_url','qr_payload','transfer_reference',
        ]));
    }

    public static function vnpay(): array
    {
        $s=config('services.vnpay',[]);
        try {
            (new VnpayProtocol($s))->assertTarget('127.0.0.1');
            foreach (['return_url','ipn_url'] as $key) {
                $url=$s[$key]??''; $host=parse_url($url,PHP_URL_HOST);
                if (parse_url($url,PHP_URL_SCHEME)!=='https' || !$host || str_ends_with($host,'.invalid') || str_ends_with($host,'.test') || $host==='localhost'
                    || (filter_var($host,FILTER_VALIDATE_IP) && !filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))) throw new \RuntimeException('Public callback endpoints required.');
            }
            if (!config('roomtype-v2.vnpay_merchant_verified',false)) throw new \RuntimeException('Merchant has not been verified.');
            return ['status'=>'CONFIGURED','tested'=>false,'blocker'=>'Merchant sandbox E2E and public IPN acceptance still required.'];
        } catch (\Throwable $e) { return ['status'=>'BLOCKED','tested'=>false,'blocker'=>'Verified merchant settings and public HTTPS Return/IPN endpoints required.']; }
    }

    public static function smtp(?string $recipient=null): array
    {
        $s=config('roomtype-v2.staging_mail');
        if (!is_array($s) || ($s['approved']??false)!==true || !filter_var($s['from_address']??'',FILTER_VALIDATE_EMAIL)
            || !is_string($s['host']??null) || trim($s['host'])==='' || !is_int($s['port']??null) || $s['port']<1 || $s['port']>65535
            || !is_array($s['recipients']??null) || count($s['recipients'])===0 || count($s['recipients'])>20) throw new \RuntimeException('Approved staging SMTP endpoint/sender/recipient allowlist required.');
        foreach ($s['recipients'] as $r) if (!filter_var($r,FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Invalid approved recipient.');
        if (isset($s['verify_peer']) && $s['verify_peer']!==true) throw new \RuntimeException('TLS certificate verification cannot be disabled.');
        if ($recipient!==null && !in_array($recipient,$s['recipients'],true)) throw new \RuntimeException('Recipient is not approved for staging delivery.');
        if (!in_array($s['encryption']??null,['tls','ssl'],true) && !in_array($s['host'],['127.0.0.1','localhost'],true)) throw new \RuntimeException('Remote SMTP requires TLS.');
        if (in_array($s['host'],['127.0.0.1','localhost'],true) && (!str_ends_with($s['from_address'],'@example.test') || array_filter($s['recipients'],fn($r)=>!str_ends_with($r,'@example.test')))) throw new \RuntimeException('Local capture transport permits only synthetic recipients.');
        return $s;
    }

    public static function readiness(): array
    {
        try { self::smtp(); $smtp=['status'=>'CONFIGURED','tested'=>false,'blocker'=>'Approved transport connection and recipient acceptance not yet proven.']; }
        catch (\Throwable $e) { $smtp=['status'=>'BLOCKED','tested'=>false,'blocker'=>'No approved staging SMTP configuration.']; }
        return ['banking'=>['status'=>self::banking()?'CONFIGURED':'BLOCKED','tested'=>false,'blocker'=>self::banking()?'Operational verification SLA required.':'not_configured'],
            'vnpay'=>self::vnpay(),'smtp'=>$smtp];
    }
}
