<?php
namespace App\Services\Places;

final class MapsUrl
{
    public static function validate(?string $value): ?string
    {
        $value=trim($value ?? '');$url=parse_url($value);
        if (!$url || ($url['scheme'] ?? '')!=='https' || isset($url['user']) || isset($url['pass']) || isset($url['port']) && $url['port']!==443 || preg_match('/[\x00-\x20\\\\]/',$value)) return null;
        $host=strtolower($url['host'] ?? '');$path=$url['path'] ?? '/';
        $allowed=in_array($host,['google.com','www.google.com','maps.google.com'],true) && ($host==='maps.google.com' || preg_match('~^/maps(?:/|$)~',$path))
            || $host==='maps.app.goo.gl' && strlen($path)>1 || $host==='goo.gl' && str_starts_with($path,'/maps/');
        return $allowed?$value:null;
    }
}
