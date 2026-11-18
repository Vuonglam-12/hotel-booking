<?php

namespace Tests\Support;

use App\Models\Attraction;
use App\Models\AttractionImage;
use App\Models\Customer;
use App\Models\GuideCategory;
use App\Models\GuidePost;
use App\Services\LegacyBlogImporter;
use Illuminate\Support\Facades\DB;

final class CatalogMysqlFixture
{
    public static function locations(): void
    {
        CatalogMysqlEnvironment::assertTarget();
        ExperienceFixture::locations();
        foreach([2,3,4] as $id)DB::table('location')->insert(['id'=>$id,'name'=>'Synthetic decoy ID '.$id,'country'=>'Vietnam']);
    }

    public static function token(string $role='admin',string $name='admin_token',array $abilities=['admin']): string
    {
        return Customer::create(['name'=>'Isolated '.$role,'role'=>$role,'email'=>bin2hex(random_bytes(8)).'@example.test',
            'password_hash'=>'synthetic-disabled-password-not-for-login'])->createToken($name,$abilities)->plainTextToken;
    }

    public static function hotels(): array
    {
        $ids=[];
        foreach([[72,'Khách sạn kiểm thử Hà Nội','active'],[405,'Khách sạn kiểm thử Phú Quốc','active'],[72,'Khách sạn kiểm thử đã ẩn','inactive']] as [$location,$name,$status])
            $ids[]=DB::table('hotel')->insertGetId(['location_id'=>$location,'name'=>$name,'status'=>$status,'address'=>'Synthetic address, isolated MySQL only','latitude'=>0,'longitude'=>0]);
        return $ids;
    }

    public static function guide(array $override=[]): GuidePost
    {
        $category=GuideCategory::firstOrCreate(['slug'=>'mysql-fixture'],['name'=>'Danh mục MySQL kiểm thử','is_active'=>true]);
        return GuidePost::create([...[
            'title'=>'Cẩm nang MySQL kiểm thử','slug'=>'mysql-guide-'.bin2hex(random_bytes(5)),
            'excerpt'=>'Dữ liệu chỉ thuộc MySQL synthetic cô lập.', 'body_html'=>'<h2>Nội dung kiểm thử</h2><p>'.str_repeat('Nội dung phục vụ kiểm thử tích hợp, không dùng trên dữ liệu thật. ',6).'</p>',
            'category_id'=>$category->id,'location_id'=>72,'author_name'=>'Tác giả fixture','status'=>'published','published_at'=>now()->subMinute(),
        ],...$override]);
    }

    public static function browser(): void
    {
        CatalogMysqlEnvironment::assertTarget();self::locations();$hotels=self::hotels();
        $stage=CatalogMysqlEnvironment::settings();$runtime=$stage['runtime'];
        mkdir($runtime.'/uploads/catalog-fixture',0777,true);
        $assets=[];
        foreach([1,2] as $number){
            $image=imagecreatetruecolor(800,500);imagefill($image,0,0,imagecolorallocate($image,225,234,246));
            imagestring($image,5,120,225,'ISOLATED MYSQL TEST IMAGE '.$number,imagecolorallocate($image,20,43,73));
            $path=$runtime.'/uploads/catalog-fixture/test-'.$number.'.png';imagepng($image,$path);imagedestroy($image);$assets[]=$path;
        }
        DB::table('hotel_image')->insert(['hotel_id'=>$hotels[0],'image_url'=>'storage/catalog-fixture/test-1.png','is_primary'=>true]);
        $ids=[];
        foreach(range(1,14) as $number){
            $location=$number%3===0?405:72;$food=$number%2===0;
            $item=ExperienceFixture::published(['name'=>'Trải nghiệm MySQL '.str_pad($number,2,'0',STR_PAD_LEFT),'slug'=>'mysql-experience-'.$number,
                'location_id'=>$location,'experience_location_code'=>$location===72?'vn-ha-noi':'vn-phu-quoc',
                'experience_category'=>$food?'am-thuc':'tham-quan','item_type'=>$food?'restaurant':'attraction',
                'experience_summary'=>'Synthetic MySQL fixture only.','experience_duration_minutes'=>75,'open_time'=>'08:00','close_time'=>'18:00']);
            $ids[]=$item->id;
            if($number===1)foreach([1,2] as $image)AttractionImage::create(['attraction_id'=>$item->id,'image_url'=>'storage/catalog-fixture/test-'.$image.'.png',
                'is_primary'=>$image===1,'caption'=>'Synthetic test image '.$image,'source_note'=>'Synthetic image generated only for the isolated test runner.']);
        }
        $report=app(LegacyBlogImporter::class)->apply();$legacy=GuidePost::where('legacy_id',1)->firstOrFail();
        foreach(range(1,12) as $number)self::guide(['title'=>'Cẩm nang MySQL '.str_pad($number,2,'0',STR_PAD_LEFT),'slug'=>'mysql-guide-'.$number,
            'is_featured'=>$number===1,'location_id'=>$number===1?72:null,'cover_path'=>$number===1?'storage/catalog-fixture/test-1.png':null]);
        $state=['synthetic_only'=>true,'database'=>$stage['database'],'admin_token'=>self::token(),'upload_file'=>$assets[0],
            'replacement_file'=>$assets[1],'experience_id'=>$ids[0],'experience_slug'=>'mysql-experience-1','guide_slug'=>'mysql-guide-1','legacy_slug'=>$legacy->slug,'import_report'=>$report];
        file_put_contents($runtime.'/browser-state.json',json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }
}
