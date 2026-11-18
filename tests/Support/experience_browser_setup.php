<?php

use App\Models\AttractionImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CatalogMysqlEnvironment;
use Tests\Support\CatalogMysqlFixture;
use Tests\Support\ExperienceFixture;

$mysql = (bool) getenv('AUREL_CATALOG_MYSQL_STAGE');
if ($mysql) {
    require __DIR__.'/catalog_mysql_bootstrap.php';
    $settings = CatalogMysqlEnvironment::settings();
    $runtime = $settings['runtime']; $database = $settings['database'];
    $app = CatalogMysqlEnvironment::application();
    CatalogMysqlEnvironment::assertTarget();
} else {
    require __DIR__.'/experience_test_bootstrap.php';
    $runtime = getenv('AUREL_EXPERIENCE_TEST_RUNTIME');
    $database = $runtime.'/experiences.sqlite';
    touch($database);
    $app = ExperienceFixture::application($runtime, $database);
    ExperienceFixture::schema();
}
ExperienceFixture::locations();
if (!is_dir($runtime.'/uploads/experience-fixture')) mkdir($runtime.'/uploads/experience-fixture', 0777, true);
// Clearly synthetic assets, only in the owned test fixture; never published to application data or assets.
$assetPaths = [];
foreach ([1, 2] as $number) {
    $image = imagecreatetruecolor(640, 420);
    imagefill($image, 0, 0, imagecolorallocate($image, 225, 234, 246));
    imagestring($image, 5, 65, 185, 'ISOLATED TEST IMAGE '.$number, imagecolorallocate($image, 20, 43, 73));
    $assetPath = $runtime.'/uploads/experience-fixture/test-'.$number.'.png';
    imagepng($image, $assetPath); imagedestroy($image); $assetPaths[] = $assetPath;
}
$categories = ['tham-quan' => 'attraction', 'am-thuc' => 'restaurant', 'vui-choi' => 'activity', 'dia-phuong' => 'activity'];
$ids = [];
foreach (range(1, 15) as $number) {
    $key = array_keys($categories)[($number-1) % 4];
    $locationId = $number % 3 === 0 ? 405 : 72;
    $item = ExperienceFixture::published([
        'name' => 'Trải nghiệm kiểm thử '.str_pad($number,2,'0',STR_PAD_LEFT), 'slug' => 'kiem-thu-'.$number,
        'experience_category' => $key, 'item_type' => $categories[$key], 'location_id' => $locationId,
        'experience_location_code' => $locationId === 405 ? 'vn-phu-quoc' : 'vn-ha-noi',
        'experience_summary' => 'Nội dung minh họa chỉ thuộc môi trường kiểm thử cô lập.',
        'address' => 'Địa chỉ kiểm thử, không công bố ra môi trường chính',
        'experience_duration_minutes' => 75, 'open_time' => '08:00', 'close_time' => '18:00',
    ]);
    $ids[] = $item->id;
    if ($number === 1) foreach ([1, 2] as $index) AttractionImage::create([
        'attraction_id' => $item->id, 'image_url' => 'storage/experience-fixture/test-'.$index.'.png', 'is_primary' => $index === 1,
        'caption' => 'Ảnh kiểm thử '.$index, 'source_note' => 'Ảnh nhân tạo chỉ dùng trong kiểm thử cô lập.',
    ]);
}
DB::table('hotel')->insert(['location_id'=>72,'name'=>'Khách sạn kiểm thử cùng địa phương','address'=>'Địa chỉ fixture','status'=>'active',
    ...($mysql ? ['latitude'=>0,'longitude'=>0] : [])]);
$stage = ['synthetic_only'=>true,'driver'=>$mysql ? 'mysql' : 'sqlite','runtime'=>$runtime,'database'=>$database,
    'admin_token'=>$mysql ? CatalogMysqlFixture::token() : ExperienceFixture::token(),
    'upload_file'=>$assetPaths[0], 'replacement_file'=>$assetPaths[1], 'ids'=>$ids, 'environment'=>$experienceEnvironment ?? []];
// Preserve the MySQL ownership descriptor; browser state is a separate file.
$stageFile = $runtime.($mysql ? '/experience-browser-state.json' : '/stage.json');
file_put_contents($stageFile, json_encode($stage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['stage_file'=>$stageFile, 'runtime'=>$runtime], JSON_THROW_ON_ERROR);
