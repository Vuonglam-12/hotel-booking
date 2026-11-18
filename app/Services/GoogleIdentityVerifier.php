<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Verify the GIS credential before any account lookup. Never send the credential to a third party. */
final class GoogleIdentityVerifier
{
    public const CERTIFICATES_URL='https://www.googleapis.com/oauth2/v1/certs';

    public function verify(string $credential): array
    {
        try {
            $parts=explode('.',$credential);
            if (count($parts)!==3 || strlen($credential)>8192) throw new \UnexpectedValueException();
            $header=json_decode($this->decode($parts[0]),true,8,JSON_THROW_ON_ERROR);
            $claims=json_decode($this->decode($parts[1]),true,8,JSON_THROW_ON_ERROR);
            $signature=$this->decode($parts[2]);
            $client=config('services.google.client_id');
            if (!is_array($header) || !is_array($claims) || ($header['alg']??null)!=='RS256'
                || !is_string($header['kid']??null) || !preg_match('/^[A-Za-z0-9_-]{1,200}$/D',$header['kid'])
                || !is_string($client) || $client==='' || ($claims['aud']??null)!==$client
                || !in_array($claims['iss']??null,['accounts.google.com','https://accounts.google.com'],true)
                || !is_int($claims['exp']??null) || $claims['exp']<=now()->timestamp
                || !is_int($claims['iat']??null) || $claims['iat']>now()->timestamp+60 || $claims['iat']>$claims['exp']
                || (isset($claims['nbf']) && (!is_int($claims['nbf']) || $claims['nbf']>now()->timestamp))
                || !is_string($claims['sub']??null) || !preg_match('/^[A-Za-z0-9_-]{1,255}$/D',$claims['sub'])
                || ($claims['email_verified']??null)!==true || !is_string($claims['email']??null)
                || strlen($claims['email'])>100 || !filter_var($claims['email'],FILTER_VALIDATE_EMAIL)) throw new \UnexpectedValueException();
            $certificates=$this->certificates();
            $certificate=$certificates[$header['kid']]??null;
            $key=is_string($certificate)?openssl_pkey_get_public($certificate):false;
            $details=$key?openssl_pkey_get_details($key):false;
            if (!$details || $details['type']!==OPENSSL_KEYTYPE_RSA || $details['bits']<2048
                || openssl_verify($parts[0].'.'.$parts[1],$signature,$key,OPENSSL_ALGO_SHA256)!==1) throw new \UnexpectedValueException();
            // Email-only legacy account linking is safe only where Google is authoritative.
            $domain=strtolower(substr(strrchr($claims['email'],'@'),1));
            if ($domain!=='gmail.com' && (!is_string($claims['hd']??null) || strtolower($claims['hd'])!==$domain)) {
                throw new \DomainException('Use the existing email/password flow for a non-hosted Google email.');
            }
            $claims['name']=is_string($claims['name']??null)?mb_substr($claims['name'],0,100):'Khách hàng';
            return $claims;
        } catch (\JsonException|\UnexpectedValueException $e) {
            throw new \UnexpectedValueException('Invalid Google identity credential.');
        }
    }

    private function decode(string $value): string
    {
        if ($value==='' || !preg_match('/^[A-Za-z0-9_-]+$/D',$value)) throw new \UnexpectedValueException();
        $decoded=base64_decode(strtr($value,'-_','+/'),true);
        if ($decoded===false) throw new \UnexpectedValueException();
        return $decoded;
    }

    private function certificates(): array
    {
        $key='aurel.google.identity.certificates.v1'; $cached=Cache::get($key);
        if (is_array($cached)) return $cached;
        try {
            $response=Http::connectTimeout(2)->timeout(5)->withOptions(['verify'=>true,'allow_redirects'=>false])->get(self::CERTIFICATES_URL);
            $certificates=$response->json();
            if (!$response->successful() || !is_array($certificates) || !$certificates || count($certificates)>64) throw new \RuntimeException();
            foreach ($certificates as $certificate) if (!is_string($certificate) || strlen($certificate)>16384 || !str_contains($certificate,'BEGIN CERTIFICATE')) throw new \RuntimeException();
            preg_match('/(?:^|[, ])max-age=(\d+)/i',$response->header('Cache-Control')??'',$maxAge);
            $seconds=min(3600,(int)($maxAge[1]??300)); if ($seconds>0) Cache::put($key,$certificates,$seconds);
            return $certificates;
        } catch (\Throwable $e) { throw new \RuntimeException('Google identity verification temporarily unavailable.'); }
    }
}
