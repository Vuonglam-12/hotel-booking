<?php

if (getenv('AUREL_CATALOG_MYSQL_STAGE')) require __DIR__.'/catalog_mysql_bootstrap.php';
else require __DIR__.'/guide_test_bootstrap.php';

use App\Models\GuideCategory;
use App\Models\GuidePost;
use App\Services\LegacyBlogImporter;
use Illuminate\Support\Facades\DB;
use Tests\Support\ExperienceFixture;

$mysql = (bool) getenv('AUREL_CATALOG_MYSQL_STAGE');
if ($mysql) {
    $owned = \Tests\Support\CatalogMysqlEnvironment::settings();
    $runtime = $owned['runtime']; $database = $owned['database']; $guideEnvironment = [];
    $app = \Tests\Support\CatalogMysqlEnvironment::application();
    \Tests\Support\CatalogMysqlEnvironment::assertTarget();
    foreach ([[72,'Hà Nội'],[901,'Hồ Chí Minh'],[18,'Đà Lạt'],[405,'Phú Quốc']] as [$id,$name])
        DB::table('location')->insert(['id'=>$id,'name'=>$name,'country'=>'Việt Nam']);
} else {
    $runtime = getenv('AUREL_GUIDE_TEST_RUNTIME');
    $database = $runtime.'/guides.sqlite'; touch($database);
    $app = ExperienceFixture::application($runtime, $database);
    ExperienceFixture::schema(); ExperienceFixture::locations();
    (require base_path('database/migrations/2026_10_23_000002_create_travel_guides.php'))->up();
}
$importReport = app(LegacyBlogImporter::class)->apply();
$legacy = GuidePost::where('legacy_id',1)->firstOrFail();
mkdir($runtime.'/uploads/guide-fixture',0777,true);
$assets=[];
foreach([1,2] as $number){
    $image=imagecreatetruecolor(800,500);imagefill($image,0,0,imagecolorallocate($image,225,234,246));
    imagestring($image,5,150,230,'ISOLATED GUIDE TEST IMAGE '.$number,imagecolorallocate($image,20,43,73));
    $path=$runtime.'/uploads/guide-fixture/test-'.$number.'.png';imagepng($image,$path);imagedestroy($image);$assets[]=$path;
}
$category=GuideCategory::create(['name'=>'Danh mục kiểm thử','slug'=>'kiem-thu','is_active'=>true]);
foreach(range(1,12) as $number) GuidePost::create([
    'title'=>'Cẩm nang kiểm thử '.str_pad($number,2,'0',STR_PAD_LEFT), 'slug'=>'huong-dan-kiem-thu-'.$number,
    'excerpt'=>'Nội dung minh họa chỉ sử dụng trong môi trường kiểm thử cô lập.',
    'body_html'=>'<h2>Góc nhìn kiểm thử</h2><p>'.str_repeat('Đây là nội dung fixture, không công bố trên hệ thống chính. ',8).'</p><ul><li>Định dạng kiểm thử</li></ul>',
    'cover_path'=>$number===1?'storage/guide-fixture/test-1.png':null,'category_id'=>$category->id,'location_id'=>$number===1?72:null,
    'author_name'=>'Tác giả kiểm thử','status'=>'published','published_at'=>now()->subMinutes($number),'is_featured'=>$number===1,
]);
if (!$mysql) ExperienceFixture::published();
DB::table('hotel')->insert(['location_id'=>72,'name'=>'Khách sạn fixture cùng địa phương','address'=>'Địa chỉ kiểm thử','status'=>'active',...($mysql?['latitude'=>0,'longitude'=>0]:[])]);
$stage=['synthetic_only'=>true,'driver'=>$mysql?'mysql':'sqlite','runtime'=>$runtime,'database'=>$database,'environment'=>$guideEnvironment,
    'admin_token'=>$mysql?\Tests\Support\CatalogMysqlFixture::token():ExperienceFixture::token(),'upload_file'=>$assets[0],'replacement_file'=>$assets[1],
    'legacy_slug'=>$legacy->slug,'detail_slug'=>'huong-dan-kiem-thu-1','import_report'=>$importReport];
$stageFile=$runtime.($mysql?'/guide-browser-stage.json':'/stage.json');file_put_contents($stageFile,json_encode($stage,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode(['stage_file'=>$stageFile,'runtime'=>$runtime],JSON_THROW_ON_ERROR);
