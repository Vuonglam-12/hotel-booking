<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';

$stageFile = getenv('AUREL_GUIDE_BROWSER_STAGE');
$stage = $stageFile && is_file($stageFile) ? json_decode(file_get_contents($stageFile), true, 512, JSON_THROW_ON_ERROR) : null;
if (!$stage || ($stage['synthetic_only'] ?? false) !== true || ($stage['driver'] ?? '') !== 'sqlite'
    || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) throw new RuntimeException('Owned loopback SQLite fixture required.');
$runtime = realpath($stage['runtime']); $database = realpath($stage['database']);
if (!$runtime || !$database || dirname($database) !== $runtime || basename($database) !== 'guides.sqlite'
    || !str_starts_with(basename($runtime), 'aurel-guide-tests-')) throw new RuntimeException('Fixture path mismatch.');
foreach ($stage['environment'] as $key => $value) { putenv($key.'='.$value); $_ENV[$key] = $_SERVER[$key] = $value; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/__guide/health') { header('Content-Type: application/json'); echo '{"synthetic_only":true,"driver":"sqlite"}'; exit; }
if ($path === '/guide-test.css') { header('Content-Type: text/css'); readfile($runtime.'/browser.css'); exit; }
if (str_starts_with($path, '/storage/')) {
    $uploads = realpath($runtime.'/uploads'); $asset = realpath($uploads.'/'.rawurldecode(substr($path, 9)));
    if ($asset && str_starts_with($asset, $uploads.DIRECTORY_SEPARATOR) && preg_match('/\.(png|jpe?g|webp)$/i', $asset)) { header('Content-Type: '.mime_content_type($asset)); readfile($asset); exit; }
    http_response_code(404); exit;
}
$public = realpath(dirname(__DIR__,2).'/public'); $asset = realpath($public.'/'.rawurldecode($path));
if ($asset && str_starts_with($asset, $public.DIRECTORY_SEPARATOR)
    && preg_match('/\.(css|js|svg|png|jpe?g|webp|ico|woff2?|ttf)$/i', $asset)) { header('Content-Type: '.mime_content_type($asset)); readfile($asset); exit; }
$app = \Tests\Support\ExperienceFixture::application($runtime, $database);
$app->make('config')->set('app.url', 'http://'.$_SERVER['HTTP_HOST']);
$app->instance(\Illuminate\Foundation\Vite::class, new class {
    public function __invoke(...$args): \Illuminate\Support\HtmlString { return new \Illuminate\Support\HtmlString('<link rel="stylesheet" href="/guide-test.css">'); }
});
$request = \Illuminate\Http\Request::capture();
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
if (str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
    $response->setContent(preg_replace('~<script\b[^>]*\bsrc="https?://[^>]*></script>|<link\b[^>]*\bhref="https?://[^>]*>~i', '', $response->getContent()));
}
$response->send(); $kernel->terminate($request, $response);
