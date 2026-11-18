<?php

require dirname(__DIR__,2).'/vendor/autoload.php';

use Tests\Support\CatalogMysqlEnvironment as Environment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$action=$argv[1]??'';
if ($action==='provision') {
    $path=realpath(getenv('AUREL_CATALOG_MYSQL_STAGE')?:'');
    if (!$path || basename($path)!=='stage.json') throw new RuntimeException('Owned descriptor required.');
    $stage=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    // Validate paths/names before opening the fresh server, even before its owner table exists.
    Environment::settings();
    $secret=json_decode(file_get_contents($stage['runtime'].'/bootstrap-secret.json'),true,512,JSON_THROW_ON_ERROR);
    $pdo=new PDO('mysql:host=127.0.0.1;port='.$stage['port'].';charset=utf8mb4','runner_bootstrap',$secret['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>2]);
    $identity=$pdo->query('SELECT @@datadir AS datadir, @@port AS port, @@server_uuid AS uuid')->fetch(PDO::FETCH_ASSOC);
    if(realpath($identity['datadir'])!==realpath($stage['datadir'])||(int)$identity['port']!==$stage['port'])throw new RuntimeException('Fresh server path/port mismatch.');
    $stage['server_uuid']=$identity['uuid'];
    $pdo->exec('CREATE DATABASE `'.$stage['database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec("CREATE USER '".$stage['username']."'@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($stage['password']));
    $grantDatabase=str_replace('_','\\_',$stage['database']);
    $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON `".$grantDatabase."`.* TO '".$stage['username']."'@'127.0.0.1'");
    $pdo->exec('USE `'.$stage['database'].'`');
    $pdo->exec('CREATE TABLE _catalog_test_owner (id INT PRIMARY KEY, owner_token VARCHAR(100) NOT NULL) ENGINE=InnoDB');
    $insert=$pdo->prepare('INSERT INTO _catalog_test_owner VALUES(1,?)');$insert->execute([$stage['owner_token']]);
    file_put_contents($path,json_encode($stage,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    $verified=Environment::verify($stage);
    file_put_contents($stage['runtime'].'/isolation.json',json_encode(['synthetic_only'=>true,'identity'=>$verified,
        'grants'=>Environment::pdo($stage)->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN),'project_env_loaded'=>false,
        'config_cache_loaded'=>false,'other_connections'=>[]],JSON_PRETTY_PRINT));
    echo "Owned MySQL identity and database grants verified.\n";exit;
}
if($action==='shutdown'){
    $stage=Environment::settings();Environment::verify($stage);
    $secret=json_decode(file_get_contents($stage['runtime'].'/bootstrap-secret.json'),true,512,JSON_THROW_ON_ERROR);
    $pdo=new PDO('mysql:host=127.0.0.1;port='.$stage['port'].';charset=utf8mb4','runner_bootstrap',$secret['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>3]);
    if($pdo->query('SELECT @@server_uuid')->fetchColumn()!==$stage['server_uuid'])throw new RuntimeException('Shutdown identity mismatch.');
    $pdo->exec('SHUTDOWN');echo "Owned MySQL shutdown requested.\n";exit;
}
$app=Environment::application();Environment::assertTarget();
if(in_array($action,['migrations','experience-migrations','guide-migrations'],true)){
    $base=['2026_04_02_013436_create_location_table.php','2026_04_02_013436_create_customer_table.php',
        '2026_04_02_013436_create_hotel_table.php','2026_04_02_013436_create_hotel_image_table.php',
        '2026_04_02_013439_add_foreign_keys_to_hotel_table.php','2026_04_02_013439_add_foreign_keys_to_hotel_image_table.php',
        '2026_05_01_152156_create_attraction_table.php'];
    $base=array_map(fn($file)=>base_path('database/migrations/'.$file),$base);
    $base[]=base_path('vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php');
    $modules=[base_path('database/migrations/2026_10_23_000001_add_experience_catalog_metadata.php'),base_path('database/migrations/2026_10_23_000002_create_travel_guides.php'),
        base_path('database/migrations/2026_10_23_000003_widen_guide_publication_datetime.php')];
    $run=function($command,$paths){
        $identity=Environment::assertTarget();
        $code=Artisan::call($command,['--database'=>'catalog_test','--path'=>$paths,'--realpath'=>true,'--force'=>true]);
        $output=Artisan::output();echo $output;
        file_put_contents(Environment::settings()['runtime'].'/migration-targets.jsonl',json_encode(['command'=>$command,'paths'=>$paths,'target'=>$identity,'code'=>$code,'output'=>$output])."\n",FILE_APPEND);
        if($code!==0)throw new RuntimeException('Scoped migration failed.');
    };
    $run('migrate',$base);
    if($action==='guide-migrations'){
        if(Schema::hasTable('guide_post')||Schema::hasTable('guide_category'))throw new RuntimeException('Expected a fresh synthetic Guide schema.');
        // Guide CRUD must work without migrating the Experience module or touching location data.
        $run('migrate',[$modules[1]]);
        $run('migrate',[$modules[2]]);
        if(!app(\App\Support\GuideSchema::class)->ready()||Schema::hasColumn('location','code')||Schema::hasTable('attraction_image'))throw new RuntimeException('Guide-only migration boundary failed.');
        $first=DB::table('migrations')->orderBy('id')->get()->toJson();
        $run('migrate',array_slice($modules,1));
        if($first!==DB::table('migrations')->orderBy('id')->get()->toJson())throw new RuntimeException('Repeated migration changed the ledger.');
        $type=DB::selectOne("SELECT DATA_TYPE AS type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='guide_post' AND COLUMN_NAME='published_at'")->type;
        if($type!=='datetime')throw new RuntimeException('Expected widened publication date.');
        file_put_contents(Environment::settings()['runtime'].'/migration-report.json',json_encode([
            'mode'=>'guides-crud','apply'=>'PASS','repeat'=>'PASS','published_at_type'=>$type,'experience_metadata_migration_ran'=>false,
            'seed_ran'=>false,'importer_ran'=>false,'catalog_migrations'=>array_slice($modules,1),'tables'=>Schema::getTableListing()],JSON_PRETTY_PRINT));
        echo "PASS Guide-only migrations 000002 -> 000003 and no-op repeat; no Experience migration, seed or importer.\n";exit;
    }
    if($action==='experience-migrations'){
        // Fresh runner-owned schema only: no seeds, importer, Guide migrations or production clone rehearsal.
        if(Schema::hasColumn('location','code')||Schema::hasTable('attraction_image'))throw new RuntimeException('Expected an unmigrated synthetic Experience schema.');
        $run('migrate',[$modules[0]]);
        if(!app(\App\Support\ExperienceSchema::class)->ready())throw new RuntimeException('Experience schema is incomplete.');
        file_put_contents(Environment::settings()['runtime'].'/migration-report.json',json_encode([
            'mode'=>'experience-crud','apply'=>'PASS','catalog_migrations'=>[$modules[0]],'seed_ran'=>false,'importer_ran'=>false,
            'guide_migrations_ran'=>false,'tables'=>Schema::getTableListing()],JSON_PRETTY_PRINT));
        echo "PASS Experience-only schema prepared on fresh owned MySQL; no seed or importer.\n";exit;
    }
    DB::table('location')->insert(['id'=>9009,'name'=>'Legacy fixture location','country'=>'Vietnam']);
    DB::table('attraction')->insert(['id'=>9009,'location_id'=>9009,'item_type'=>'attraction','name'=>'Existing synthetic legacy row','is_active'=>true,'description'=>'Baseline preserved through additive migrations']);
    $before=DB::table('attraction')->where('id',9009)->first();
    $run('migrate',array_slice($modules,0,2));
    $categoryId=DB::table('guide_category')->insertGetId(['name'=>'Upgrade preservation fixture','slug'=>'upgrade-preservation']);
    $guideId=DB::table('guide_post')->insertGetId(['title'=>'Pre-upgrade synthetic draft','slug'=>'upgrade-preservation','category_id'=>$categoryId,'published_at'=>'2026-04-06 00:00:00']);
    $guideBefore=DB::table('guide_post')->where('id',$guideId)->first();
    $run('migrate',[$modules[2]]);
    if(json_encode($guideBefore)!==json_encode(DB::table('guide_post')->where('id',$guideId)->first()))throw new RuntimeException('Publication-date widening changed existing data.');
    $dateType=DB::selectOne("SELECT DATA_TYPE AS type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='guide_post' AND COLUMN_NAME='published_at'")->type;
    if($dateType!=='datetime')throw new RuntimeException('Publication date was not widened.');
    DB::table('guide_post')->where('id',$guideId)->delete();DB::table('guide_category')->where('id',$categoryId)->delete();
    $first=DB::table('migrations')->orderBy('id')->get()->toJson();
    $run('migrate',$modules);$second=DB::table('migrations')->orderBy('id')->get()->toJson();
    if($first!==$second)throw new RuntimeException('Repeated migrate changed migration records.');
    $futureId=DB::table('guide_post')->insertGetId(['title'=>'Future rollback preservation fixture','slug'=>'future-rollback-preservation','published_at'=>'2099-01-02 00:30:00']);
    $run('migrate:rollback',$modules);
    if(DB::table('guide_post')->where('id',$futureId)->value('published_at')!=='2099-01-02 00:30:00')throw new RuntimeException('Upgrade rollback changed future publication data.');
    $run('migrate:rollback',$modules);
    if(Schema::hasTable('guide_post')||Schema::hasTable('attraction_image')||Schema::hasColumn('location','code')
        ||json_encode($before)!==json_encode(DB::table('attraction')->where('id',9009)->first()))throw new RuntimeException('Rollback failed or legacy row changed.');
    $run('migrate',$modules);
    file_put_contents(Environment::settings()['runtime'].'/migration-report.json',json_encode(['apply'=>'PASS','repeat'=>'PASS','rollback'=>'PASS','reapply'=>'PASS','legacy_row_preserved'=>true,'existing_guide_preserved_on_datetime_upgrade'=>true,'upgrade_rollback_preserved_future_date'=>true,'published_at_type'=>$dateType,
        'tables'=>Schema::getTableListing(),'indexes'=>DB::select('SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX'),
        'foreign_keys'=>DB::select('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')],JSON_PRETTY_PRINT));
    echo "PASS scoped MySQL migrations, no-op repeat, rollback/reapply and preserved legacy row.\n";exit;
}
if($action==='browser-fixture'){
    \Tests\Support\CatalogMysqlFixture::browser();echo "PASS shared MySQL browser fixture prepared.\n";exit;
}
if(in_array($action,['fault-on','fault-off'],true)){
    $module=$argv[2]??'';
    $table=match($module){'guides'=>'guide_post','experiences'=>'attraction_image',default=>throw new RuntimeException('Unknown fault target.')};
    Environment::assertTarget();
    if($action==='fault-on')Schema::rename($table,'_catalog_hidden_'.$table);else Schema::rename('_catalog_hidden_'.$table,$table);
    echo "Owned test schema fault: ".$action.' / '.$module."\n";exit;
}
throw new RuntimeException('Unknown catalog control action.');
