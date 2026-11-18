<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Support\RoomTypeV2TestDatabase;
use Symfony\Component\Process\Process;

if (!isset($argv[1])) throw new RuntimeException('Pass owned test-environment.json; application .env is not used for database settings.');
$path = realpath($argv[1]);
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS=' . $path);
$settings = RoomTypeV2TestDatabase::settings();
RoomTypeV2TestDatabase::validate(array_merge($settings, ['driver' => 'mysql']), true);
$arguments = [];
for ($i = 2; $i < count($argv); $i++) {
    if ($argv[$i] === '--filter' && isset($argv[$i + 1])) { $arguments[] = '--filter'; $arguments[] = $argv[++$i]; }
    elseif (in_array($argv[$i], ['--stop-on-error', '--stop-on-failure'], true)) $arguments[] = $argv[$i];
    else throw new RuntimeException('Only filter/stop flags allowed; database/bootstrap/config overrides refused.');
}
$root = dirname(__DIR__, 2);
$process = new Process(array_merge([PHP_BINARY, $root . '/vendor/bin/phpunit', '-c', $root . '/phpunit.roomtype-v2-backend.xml', '--no-progress'], $arguments), $root,
    ['AUREL_ROOMTYPE_V2_TEST_SETTINGS' => $path]);
$process->setTimeout(600);
$code = $process->run(function ($type, $output) { echo $output; });
file_put_contents(dirname($path) . '/last-backend-test-output.txt', $process->getOutput() . $process->getErrorOutput());
exit($code);
