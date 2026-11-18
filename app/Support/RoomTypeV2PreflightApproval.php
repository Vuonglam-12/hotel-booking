<?php
namespace App\Support;

/** Pure validation before PDO. Does not read .env or authorize a write. */
final class RoomTypeV2PreflightApproval
{
    public static function validate(array $approval,?string $confirmation): array
    {
        $target=$approval['target']??[];
        $port=$target['port']??null;
        if (($approval['approved_read_only']??false)!==true || ($approval['action']??null)!=='roomtype_v2_preflight'
            || !is_string($approval['expires_at']??null) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/D',$approval['expires_at'])
            || strtotime($approval['expires_at'])<=time() || strtotime($approval['expires_at'])>time()+86400
            || !is_string($approval['server_uuid']??null) || !preg_match('/^[a-f0-9-]{36}$/D',$approval['server_uuid'])
            || !is_string($target['database']??null) || !preg_match('/^[a-zA-Z0-9_]+$/D',$target['database'])
            || !(is_int($port)||(is_string($port)&&ctype_digit($port))) || (int)$port<1 || (int)$port>65535
            || !is_string($target['host']??null) || !preg_match('/^[a-zA-Z0-9.:-]+$/D',$target['host'])
            || !is_string($target['username']??null) || $target['username']==='' || strtolower($target['username'])==='root'
            || !is_string($target['password']??null) || $target['password']===''
            || $confirmation!==$target['host'].':'.$target['port'].'/'.$target['database']) {
            throw new \RuntimeException('Target/read permission approval mismatch; no connection opened.');
        }
        if (!in_array($target['host'],['127.0.0.1','::1','localhost'],true)
            && (!is_string($target['ssl_ca']??null) || !is_file($target['ssl_ca']) || !is_readable($target['ssl_ca']))) {
            throw new \RuntimeException('Remote preflight requires an approved TLS CA before connecting.');
        }
        return $target;
    }
}
