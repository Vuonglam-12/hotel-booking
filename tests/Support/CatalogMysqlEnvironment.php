<?php

namespace Tests\Support;

use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PDO;
use RuntimeException;

/** No project .env, fallback connection, or access to an existing MySQL instance. */
final class CatalogMysqlEnvironment
{
    public static function settings(): array
    {
        $path = realpath(getenv('AUREL_CATALOG_MYSQL_STAGE') ?: '');
        if (!$path || basename($path) !== 'stage.json') throw new RuntimeException('Explicit owned MySQL descriptor required.');
        $stage = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $runtime = realpath($stage['runtime'] ?? '');
        $temp = realpath(sys_get_temp_dir());
        if (!$runtime || dirname($path) !== $runtime || dirname($runtime) !== $temp
            || !preg_match('/^aurel-catalog-mysql-([a-f0-9]{12})$/D', basename($runtime), $match)
            || ($stage['nonce'] ?? '') !== $match[1] || ($stage['synthetic_only'] ?? null) !== true
            || ($stage['host'] ?? '') !== '127.0.0.1' || !is_int($stage['port'] ?? null) || $stage['port'] < 10000
            || ($stage['database'] ?? '') !== 'aurel_catalog_test_'.$stage['nonce']
            || ($stage['username'] ?? '') !== 'aurel_'.$stage['nonce']
            || realpath($stage['datadir'] ?? '') !== $runtime.DIRECTORY_SEPARATOR.'data'
            || !hash_equals(trim(file_get_contents($runtime.'/owner.txt')), $stage['owner_token'] ?? '')) {
            throw new RuntimeException('MySQL ownership descriptor rejected before connecting.');
        }
        return $stage;
    }

    public static function pdo(array $stage): PDO
    {
        return new PDO('mysql:host=127.0.0.1;port='.$stage['port'].';dbname='.$stage['database'].';charset=utf8mb4',
            $stage['username'], $stage['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT=>3, PDO::ATTR_EMULATE_PREPARES=>false]);
    }

    /** Executed before each migration/rollback, fixture setup, test application and HTTP request. */
    public static function verify(array $stage, ?PDO $pdo = null): array
    {
        $pdo ??= self::pdo($stage);
        $identity = $pdo->query('SELECT DATABASE() AS db, @@port AS port, @@datadir AS datadir, @@server_uuid AS uuid, VERSION() AS version, CURRENT_USER() AS account, @@sql_mode AS sql_mode, @@bind_address AS bind_address, @@log_bin AS log_bin, @@event_scheduler AS event_scheduler')->fetch(PDO::FETCH_ASSOC);
        if ($identity['db'] !== $stage['database'] || (int)$identity['port'] !== $stage['port']
            || realpath($identity['datadir']) !== realpath($stage['datadir']) || $identity['uuid'] !== $stage['server_uuid']
            || $identity['bind_address'] !== '127.0.0.1' || (int)$identity['log_bin'] !== 0) throw new RuntimeException('MySQL server identity mismatch.');
        $owner = $pdo->query('SELECT owner_token FROM _catalog_test_owner WHERE id=1')->fetchColumn();
        if (!is_string($owner) || !hash_equals($stage['owner_token'], $owner)) throw new RuntimeException('MySQL owner marker mismatch.');
        return $identity;
    }

    public static function environment(array $stage): void
    {
        $runtime = $stage['runtime'];
        $values = ['APP_ENV'=>'testing','APP_DEBUG'=>'false','APP_URL'=>'http://localhost',
            'APP_KEY'=>'base64:'.base64_encode(str_repeat('m',32)), 'APP_CONFIG_CACHE'=>$runtime.'/absent-config.php',
            'APP_SERVICES_CACHE'=>$runtime.'/services.php','APP_PACKAGES_CACHE'=>$runtime.'/packages.php',
            'APP_ROUTES_CACHE'=>$runtime.'/absent-routes.php','APP_EVENTS_CACHE'=>$runtime.'/absent-events.php',
            'DB_CONNECTION'=>'catalog_test','DB_DATABASE'=>$stage['database'],'DB_HOST'=>'127.0.0.1','DB_PORT'=>(string)$stage['port'],
            'DATABASE_URL'=>'','DB_URL'=>'','CACHE_DRIVER'=>'array','SESSION_DRIVER'=>'array','QUEUE_CONNECTION'=>'sync',
            'MAIL_MAILER'=>'array','LOG_CHANNEL'=>'stderr'];
        foreach ($values as $key=>$value) { putenv($key.'='.$value); $_ENV[$key]=$_SERVER[$key]=$value; }
    }

    public static function application()
    {
        $stage=self::settings(); self::verify($stage); self::environment($stage); $runtime=$stage['runtime'];
        $app=require dirname(__DIR__,2).'/bootstrap/app.php';
        if (preg_match('/^[A-Za-z]:/',$runtime)) $app->addAbsoluteCachePathPrefix(substr($runtime,0,2));
        $app->useEnvironmentPath($runtime)->loadEnvironmentFrom('absent.env');
        $app->afterBootstrapping(LoadConfiguration::class,function($app)use($stage,$runtime){
            $app['config']->set('database',['default'=>'catalog_test','connections'=>['catalog_test'=>[
                'driver'=>'mysql','host'=>'127.0.0.1','port'=>$stage['port'],'database'=>$stage['database'],
                'username'=>$stage['username'],'password'=>$stage['password'],'unix_socket'=>'','charset'=>'utf8mb4',
                'collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true,'engine'=>'InnoDB',
                'options'=>[PDO::ATTR_TIMEOUT=>3,PDO::ATTR_EMULATE_PREPARES=>false],
            ]],'migrations'=>'migrations']);
            $app['config']->set('view.compiled',$runtime.'/views');
            $app['config']->set('filesystems.disks.public',['driver'=>'local','root'=>$runtime.'/uploads','url'=>'/storage','visibility'=>'public','throw'=>false]);
            $app['config']->set('roomtype-v2.prepared_routes',false);
            $app['config']->set('roomtype-v2.production_manifest',null);
        });
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Mail::fake(); Notification::fake(); Http::preventStrayRequests();
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Database\Events\MigrationStarted::class, function($event){
            $identity=self::assertTarget();
            file_put_contents(self::settings()['runtime'].'/migration-start-guards.jsonl',json_encode([
                'method'=>$event->method,'migration'=>get_class($event->migration),'identity'=>$identity])."\n",FILE_APPEND);
        });
        return $app;
    }

    public static function assertTarget(): array
    {
        $stage=self::settings();
        if (config('database.default') !== 'catalog_test' || array_keys(config('database.connections')) !== ['catalog_test']
            || \Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== $stage['database']) throw new RuntimeException('Application connection mismatch.');
        return self::verify($stage,\Illuminate\Support\Facades\DB::connection()->getPdo());
    }
}
