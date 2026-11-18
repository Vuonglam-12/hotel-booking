<?php

// Render the real shared layout without bootstrap/app.php, .env, an HTTP kernel or a database provider.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\HtmlString;
use Illuminate\View\ViewServiceProvider;

[$script, $runtime, $origin, $path] = $argv;
if (!is_dir($runtime) || !preg_match('~^http://127\.0\.0\.1:\d+$~', $origin)) {
    throw new RuntimeException('An isolated runtime and loopback origin are required.');
}
$root = dirname(__DIR__, 2);
$app = new Application($root);
$app->instance('config', new Repository([
    'app' => ['url' => $origin, 'debug' => false],
    'brand' => require $root.'/config/brand.php',
    'view' => ['paths' => [$root.'/resources/views'], 'compiled' => $runtime.'/views'],
    'database' => ['default' => 'disabled', 'connections' => []],
]));
$app->bind('db', static fn () => throw new RuntimeException('Database access is forbidden in the header preview.'));
$app->instance('files', new Filesystem());
$app->usePublicPath($root.'/public');
$request = Request::create($origin.$path);
$app->instance('request', $request);
$router = $app->make(Router::class);
$definitions = ['home' => '/', 'blog' => '/blog', 'blog.detail' => '/blog/{id}', 'deals' => '/deals', 'hotel.detail' => '/hotels/{id}'];
if (in_array('--future-routes', $argv, true)) {
    $definitions += ['experiences.index' => '/trai-nghiem', 'guides.index' => '/cam-nang'];
}
foreach ($definitions as $name => $uri) {
    $router->get($uri, static fn () => 'Synthetic header preview')->name($name);
}
$router->getRoutes()->refreshNameLookups();
$route = $router->getRoutes()->match($request);
$request->setRouteResolver(static fn () => $route);
$app->instance('url', new UrlGenerator($router->getRoutes(), $request));
Facade::setFacadeApplication($app);
$app->instance(Vite::class, new class {
    public function __invoke(...$args): HtmlString
    {
        return new HtmlString('<link rel="stylesheet" href="/header-test.css">');
    }
});
$app->register(ViewServiceProvider::class);
if (!is_dir($runtime.'/views')) {
    mkdir($runtime.'/views', 0777, true);
}
$factory = $app->make('view');
$factory->startSection('title', 'AUREL header preview');
$factory->startSection('content', new HtmlString('<section id="header-test-banner" style="height:1200px;background:linear-gradient(#e7eff9,#f8f8f8);padding:32px"><h1>AUREL</h1><button id="outside-header">Nội dung trang</button></section>'));
$data = [];
if ($route->getName() === 'home') {
    $data['locations'] = collect(['Hà Nội', 'Hồ Chí Minh', 'Đà Nẵng', 'Phú Quốc', 'Nha Trang', 'Hội An', 'Đà Lạt', 'Huế'])
        ->map(static fn ($name) => (object) ['name' => $name]);
}
$html = $factory->make('Layout', $data)->render();
// External fonts and Swiper are unrelated to these checks. Serve every asset from the isolated fixture.
$html = preg_replace('~<script\b[^>]*\bsrc="https?://[^>]*></script>|<link\b[^>]*\bhref="https?://[^>]*>~i', '', $html);
echo $html;
