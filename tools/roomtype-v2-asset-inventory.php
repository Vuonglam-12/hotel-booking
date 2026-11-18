<?php

// Offline inventory only: no Laravel, .env, PDO, credentials or remote storage access.
$root=realpath(dirname(__DIR__));
$options=getopt('',['output:']); $output=$options['output']??null;
$private=realpath($root.'/storage/app/private');
$directory=is_string($output)?realpath(dirname($output)):false;
$inside=static fn(string $path,string $parent): bool => str_starts_with(str_replace('\\','/',$path),str_replace('\\','/',$parent).'/');
if (!$private || !$directory || !$inside($directory,$private) || is_link($output) || file_exists($output)) {
    throw new RuntimeException('New inventory output must be inside private storage.');
}
$files=[]; $roots=[]; $bytes=0;
foreach (['public/image','storage/app/public','public/build','public/fonts','public/vendor'] as $relative) {
    $absolute=realpath($root.'/'.$relative);
    if (!$absolute) { $roots[$relative]='absent'; continue; }
    if (!$inside($absolute,$root) || is_link($root.'/'.$relative)) throw new RuntimeException('Asset root escapes workspace or is a link.');
    $roots[$relative]='present';
    $scan=function(string $folder) use (&$scan,&$files,&$bytes,$inside,$absolute,$root): void {
        foreach (new DirectoryIterator($folder) as $entry) {
            if ($entry->isDot()) continue;
            $resolved=$entry->getRealPath();
            if (!$resolved || !$inside($resolved,$absolute) || $entry->isLink()) throw new RuntimeException('Linked or escaping asset entry requires separate review.');
            if ($entry->isDir()) { $scan($resolved); continue; }
            if (!$entry->isFile() || !$entry->isReadable()) throw new RuntimeException('Unreadable asset.');
            $before=stat($resolved); $hash=hash_file('sha256',$resolved); clearstatcache(true,$resolved); $after=stat($resolved);
            if (!$hash || $before['size']!==$after['size'] || $before['mtime']!==$after['mtime']) throw new RuntimeException('Asset drift during inventory.');
            $key=substr(str_replace('\\','/',$resolved),strlen(str_replace('\\','/',$root))+1);
            $files[$key]=['bytes'=>$before['size'],'sha256'=>$hash]; $bytes+=$before['size'];
        }
    };
    $scan($absolute);
}
ksort($files); $inventoryHash=hash('sha256',json_encode($files,JSON_THROW_ON_ERROR));
$report=['offline_only'=>true,'primary_database_connected'=>false,'backup_verified'=>false,'database_reference_coverage'=>'not_verified',
    'remote_object_storage_coverage'=>'not_verified','created_at'=>gmdate(DATE_ATOM),'roots'=>$roots,
    'excluded_duplicate_link'=>'public/storage','file_count'=>count($files),'total_bytes'=>$bytes,'inventory_sha256'=>$inventoryHash,'files'=>$files];
if (file_put_contents($output,json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Inventory write failed.');
echo json_encode(array_intersect_key($report,array_flip(['offline_only','primary_database_connected','backup_verified','file_count','total_bytes','inventory_sha256'])));
