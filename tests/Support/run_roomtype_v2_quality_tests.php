<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$path=isset($argv[1])?realpath($argv[1]):false;
if (!$path) throw new RuntimeException('Owned isolated descriptor required.');
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS='.$path);
App\Support\RoomTypeV2TestDatabase::validate(array_merge(App\Support\RoomTypeV2TestDatabase::settings(),['driver'=>'mysql']),true);
$args=[];
for ($i=2;$i<count($argv);$i++) {
    if ($argv[$i]==='--filter' && isset($argv[$i+1])) { $args[]='--filter'; $args[]=$argv[++$i]; }
    elseif (in_array($argv[$i],['--stop-on-error','--stop-on-failure'],true)) $args[]=$argv[$i];
    else throw new RuntimeException('Only filter/stop flags allowed.');
}
$root=dirname(__DIR__,2);
$process=new Symfony\Component\Process\Process(array_merge([PHP_BINARY,$root.'/vendor/bin/phpunit','-c',$root.'/phpunit.roomtype-v2-quality.xml','--no-progress'],$args),$root,['AUREL_ROOMTYPE_V2_TEST_SETTINGS'=>$path]);
$process->setTimeout(900); $code=$process->run(function ($type,$output) { echo $output; });
file_put_contents(dirname($path).'/phase2d-tests-'.bin2hex(random_bytes(6)).'.txt',$process->getOutput().$process->getErrorOutput()); exit($code);
