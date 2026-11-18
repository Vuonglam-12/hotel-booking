<?php

require dirname(__DIR__,2).'/vendor/autoload.php';
if (!isset($argv[1])) throw new RuntimeException('Pass an owned isolated test descriptor.');
$path=realpath($argv[1]); putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.$path);
App\Support\RoomTypeV2TestDatabase::validate(array_merge(App\Support\RoomTypeV2TestDatabase::settings(),['driver'=>'mysql']),true);
$args=[];
for ($i=2;$i<count($argv);$i++) {
    if ($argv[$i]==='--filter' && isset($argv[$i+1])) { $args[]='--filter'; $args[]=$argv[++$i]; }
    elseif (in_array($argv[$i],['--stop-on-error','--stop-on-failure'],true)) $args[]=$argv[$i];
    else throw new RuntimeException('Only filter/stop flags are allowed.');
}
$root=dirname(__DIR__,2);
$process=new Symfony\Component\Process\Process(array_merge([PHP_BINARY,$root.'/vendor/bin/phpunit','-c',$root.'/phpunit.roomtype-v2-final.xml','--no-progress'],$args),$root,['AUREL_ROOMTYPE_V2_TEST_SETTINGS'=>$path]);
$process->setTimeout(900); $code=$process->run(function ($type,$output) { echo $output; });
$log=dirname($path).'/'.(in_array('--filter',$args,true)?'filtered-final-'.bin2hex(random_bytes(6)):'full-final').'-test-output.txt';
file_put_contents($log,$process->getOutput().$process->getErrorOutput()); exit($code);
