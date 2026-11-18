<?php
namespace App\Support;

/** A schema grant pattern must represent one literal database, not a wildcard or role. */
final class RoomTypeV2DatabasePrivileges
{
    public static function assert(array $grants,string $database,array $allowed,bool $partialRevokes): void
    {
        $schemaGrant=false;
        foreach ($grants as $grant) {
            if (preg_match('/^GRANT USAGE ON \*\.\* TO /D',$grant) && !str_contains($grant,'WITH GRANT OPTION')) continue;
            if (!preg_match('/^GRANT ([A-Z_, ]+) ON `([^`]+)`\.\* TO /D',$grant,$match)
                || str_contains($grant,'WITH GRANT OPTION')) throw new \RuntimeException('Dedicated schema-only database privileges required.');
            $pattern=$match[2]; $literal='';
            for ($i=0;$i<strlen($pattern);$i++) {
                if ($pattern[$i]==='\\') {
                    if (++$i>=strlen($pattern) || !in_array($pattern[$i],['_','%','\\'],true)) throw new \RuntimeException('Ambiguous grant pattern.');
                    $literal.=$pattern[$i];
                } else {
                    if (!$partialRevokes && in_array($pattern[$i],['_','%'],true)) throw new \RuntimeException('Wildcard schema grant refused.');
                    $literal.=$pattern[$i];
                }
            }
            if ($literal!==$database || array_diff(explode(', ',$match[1]),$allowed)) throw new \RuntimeException('Database privilege scope differs from the approved target.');
            $schemaGrant=true;
        }
        if (!$schemaGrant) throw new \RuntimeException('An explicit grant on the approved schema is required.');
    }
}
