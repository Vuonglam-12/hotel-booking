<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Support\RoomTypeV2TestDatabase;
use Symfony\Component\Process\Process;

if (!isset($argv[1])) throw new RuntimeException('Pass the owned test-environment.json path; this runner does not read application .env.');
$path = realpath($argv[1]);
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS=' . $path);
$settings = RoomTypeV2TestDatabase::settings();
RoomTypeV2TestDatabase::validate(array_merge($settings, ['driver' => 'mysql']), true);
$arguments = [];
for ($index = 2; $index < count($argv); $index++) {
    if ($argv[$index] === '--filter' && isset($argv[$index + 1])) {
        $arguments[] = '--filter'; $arguments[] = $argv[++$index];
    } elseif (in_array($argv[$index], ['--stop-on-error', '--stop-on-failure'], true)) {
        $arguments[] = $argv[$index];
    } else throw new RuntimeException('Only filter/stop flags accepted; bootstrap/config cannot be overridden.');
}
$root = dirname(__DIR__, 2);
$process = new Process(array_merge([PHP_BINARY, $root . '/vendor/bin/phpunit', '-c', $root . '/phpunit.roomtype-v2.xml', '--no-progress'], $arguments), $root,
    ['AUREL_ROOMTYPE_V2_TEST_SETTINGS' => $path]);
$process->setTimeout(300);
$code = $process->run(function ($type, $output) { echo $output; });
file_put_contents(dirname($path) . '/last-test-output.txt', $process->getOutput() . $process->getErrorOutput());
exit($code);
