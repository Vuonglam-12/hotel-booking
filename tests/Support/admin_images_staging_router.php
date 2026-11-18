<?php

$stageFile = getenv('AUREL_ADMIN_IMAGES_STAGING');
$stage = $stageFile && is_file($stageFile) ? json_decode(file_get_contents($stageFile), true, 512, JSON_THROW_ON_ERROR) : null;
if (!$stage || ($stage['synthetic_only'] ?? false) !== true || ($stage['image_only'] ?? false) !== true) throw new RuntimeException('Image-only isolated staging descriptor required.');
putenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS=' . $stage['settings_file']);
putenv('AUREL_ROOMTYPE_V2_CASE_DATABASE=' . $stage['database']);
require __DIR__ . '/roomtype_v2_test_bootstrap.php';
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$public = realpath(dirname(__DIR__, 2) . '/public');
$file = realpath($public . '/' . rawurldecode($path));
// Fixture images can stay private when the workspace no longer contains legacy demo files.
if (str_starts_with($path, '/image/')) {
    $fixtureRoot=realpath($stage['runtime'].'/image-public/image');
    $fixture=$fixtureRoot?realpath($fixtureRoot.'/'.rawurldecode(substr($path,7))):false;
    if($fixture&&str_starts_with($fixture,$fixtureRoot.DIRECTORY_SEPARATOR)&&preg_match('/\.(png|jpe?g|webp|svg)$/i',$fixture)){
        header('Content-Type: '.mime_content_type($fixture));readfile($fixture);exit;
    }
}
if ($file && str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $public) . '/') && preg_match('/\.(css|js|svg|png|jpe?g|webp|ico|woff2?|ttf)$/i', $file) && !str_starts_with($path, '/storage/')) return false;
if (str_starts_with($path, '/storage/')) {
    $root = realpath($stage['runtime'] . '/image-uploads');
    $asset = realpath($root . '/' . rawurldecode(substr($path, 9)));
    if ($asset && str_starts_with($asset, $root . DIRECTORY_SEPARATOR) && preg_match('/\.(png|jpe?g|webp)$/i', $asset)) {
        header('Content-Type: ' . mime_content_type($asset)); readfile($asset); exit;
    }
    http_response_code(404); exit;
}
if ($path === '/__images/health') { header('Content-Type: application/json'); echo '{"image_only":true,"checkout_enabled":false}'; exit; }
$_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['SCRIPT_FILENAME'] = $public . '/index.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->usePublicPath($stage['runtime'] . '/image-public');
$app->useStoragePath($stage['runtime'] . '/image-storage');
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class); $kernel->bootstrap();
config(['app.debug' => false, 'app.url' => 'http://' . $_SERVER['HTTP_HOST'], 'view.compiled' => $stage['runtime'] . '/image-views',
    'filesystems.disks.public.root' => $stage['runtime'] . '/image-uploads',
    'roomtype-v2.staging_checkout' => false, 'roomtype-v2.production_manifest' => null]);
if (!is_dir($stage['runtime'] . '/image-views')) mkdir($stage['runtime'] . '/image-views', 0700, true);
$app->make(Illuminate\Foundation\Vite::class)->useHotFile($stage['runtime'] . '/image-absent-hot');
Tests\Support\AdminImagesFixture::routes();
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request); $response->send(); $kernel->terminate($request, $response);
