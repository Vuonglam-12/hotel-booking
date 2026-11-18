<?php

namespace Tests\Feature;

use App\Models\Attraction;
use App\Models\GuideCategory;
use App\Models\GuidePost;
use App\Services\LegacyBlogImporter;
use App\Support\LegacyBlogCatalog;
use Database\Seeders\AttractionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CatalogMysqlEnvironment as Environment;
use Tests\Support\CatalogMysqlFixture as Fixture;
use Tests\Support\ExperienceFixture;

final class CatalogMysqlIntegrationTest extends TestCase
{
    public function createApplication() { return Environment::application(); }
    protected function setUp(): void
    {
        parent::setUp();Environment::assertTarget();DB::beginTransaction();Fixture::locations();$this->withoutVite();
    }
    protected function tearDown(): void
    {
        try { Mail::assertNothingSent();Notification::assertNothingSent();Http::assertNothingSent(); }
        finally { while(DB::transactionLevel()>0)DB::rollBack();parent::tearDown(); }
    }
    private function admin(): static { return $this->withToken(Fixture::token()); }
    private function experience(array $override=[]): Attraction { return ExperienceFixture::published(['slug'=>'mysql-exp-'.bin2hex(random_bytes(5)),...$override]); }
    private function guideData(array $override=[]): array
    {
        $category=GuideCategory::firstOrCreate(['slug'=>'integration'],['name'=>'Danh mục tích hợp','is_active'=>true]);
        return [...['title'=>'Bài viết MySQL synthetic','excerpt'=>'Tóm tắt kiểm thử tích hợp trên MySQL cô lập.',
            'body_html'=>'<h2>Nội dung thử nghiệm</h2><p>'.str_repeat('Dữ liệu chỉ sử dụng trong MySQL do test runner sở hữu. ',5).'</p>',
            'category_id'=>$category->id,'location_id'=>72,'author_name'=>'Tác giả fixture','status'=>'published'],...$override];
    }
    private function databaseRejects(callable $operation,int $code): void
    {
        try { $operation();$this->fail('MySQL should reject the invalid relation or duplicate unique key.'); }
        catch(QueryException $error){
            if($code===1452){$this->assertSame('23000',$error->errorInfo[0]);$this->assertContains((int)$error->errorInfo[1],[1216,1452]);}
            else $this->assertSame($code,(int)$error->errorInfo[1]);
        }
    }

    public function test_experience_crud_admin_uploads_edits_and_replaces_images_visible_to_customers(): void
    {
        $this->admin();
        $identity=Environment::assertTarget();
        $this->assertSame('8.0.30',$identity['version']);
        $this->assertSame(['catalog_test'],array_keys(config('database.connections')));
        $this->assertStringContainsString('STRICT_TRANS_TABLES',$identity['sql_mode']);
        $locations=DB::table('location')->orderBy('id')->get()->toJson();
        $created=$this->post('/api/admin/locations/72/attractions',[
            'name'=>'CRUD experience fixture','item_type'=>'attraction','experience_category'=>'tham-quan',
            'description'=>'Initial isolated CRUD content.','image_source'=>'Synthetic test image, runner only.',
            'images'=>[UploadedFile::fake()->image('initial.png',100,80)],
        ],['Accept'=>'application/json'])->assertCreated()->assertJsonPath('is_active',false)->json();
        $this->assertNull($created['experience_published_at']);
        $this->assertCount(1,$created['images']);
        $originalPath=DB::table('attraction_image')->where('id',$created['images'][0]['id'])->value('image_url');
        $originalPath=preg_replace('~^storage/~','',$originalPath);
        $this->assertTrue(Storage::disk('public')->exists($originalPath));
        $this->getJson('/api/experiences/'.$created['slug'])->assertNotFound();

        $this->putJson('/api/admin/attractions/'.$created['id'],[
            'experience_source_url'=>'https://example.test/crud-source','is_active'=>true,'verify'=>true,'publish'=>true,
            'open_time'=>'08:30','close_time'=>'18:00',
        ])->assertOk();
        $this->getJson('/api/experiences/'.$created['slug'])->assertOk()
            ->assertJsonPath('data.name','CRUD experience fixture')->assertJsonCount(1,'data.gallery');

        // Updating a published item is explicit. Both the new fields and cover must reach Customer after saving.
        $updated=$this->post('/api/admin/attractions/'.$created['id'],[
            '_method'=>'PUT','name'=>'CRUD experience edited','description'=>'Updated isolated CRUD content.',
            'address'=>'Isolated test address','location_id'=>405,'verify'=>1,'publish'=>1,
            'image_source'=>'Synthetic replacement image, runner only.',
            'image_changes'=>json_encode(['delete_ids'=>[$created['images'][0]['id']],'primary_new'=>0]),
            'images'=>[UploadedFile::fake()->image('replacement.png',120,90)],
        ],['Accept'=>'application/json'])->assertOk()->assertJsonPath('id',$created['id'])
            ->assertJsonPath('slug',$created['slug'])->assertJsonPath('location_id',405)->json();
        $this->assertCount(1,$updated['images']);
        $this->assertNotSame($created['images'][0]['id'],$updated['images'][0]['id']);
        $this->assertTrue(Storage::disk('public')->exists($originalPath),'Replacing a gallery link must retain its old asset.');
        $this->getJson('/api/experiences/'.$created['slug'])->assertOk()
            ->assertJsonPath('data.name','CRUD experience edited')->assertJsonPath('data.address','Isolated test address')
            ->assertJsonPath('data.description','Updated isolated CRUD content.')->assertJsonPath('data.location.code','vn-phu-quoc')
            ->assertJsonPath('data.cover.url',$updated['images'][0]['display_url'])->assertJsonCount(1,'data.gallery');
        $this->get('/trai-nghiem/'.$created['slug'])->assertOk()->assertSee('CRUD experience edited')
            ->assertSee('Updated isolated CRUD content.')->assertSee('Isolated test address')->assertSee($updated['images'][0]['display_url'],false);
        $this->assertSame('08:30',DB::table('attraction')->where('id',$created['id'])->value('open_time'));
        $this->assertSame($locations,DB::table('location')->orderBy('id')->get()->toJson(),'Saving an experience must not create or edit locations.');
        $this->assertSame(1,Attraction::count(),'CRUD must not seed additional catalog items.');
        $this->assertSame(0,DB::table('hotel')->count());
    }

    public function test_experience_crud_uses_existing_locations_and_rejects_invalid_or_unreviewed_publication(): void
    {
        $this->admin();
        $locations=DB::table('location')->orderBy('id')->get()->toJson();
        $this->getJson('/api/admin/locations')->assertOk()->assertJsonCount(7);
        $this->postJson('/api/admin/locations/999999/attractions',['name'=>'Invalid location','item_type'=>'activity'])->assertNotFound();
        $this->assertSame(0,Attraction::count());
        $id=$this->postJson('/api/admin/locations/2/attractions',[
            'name'=>'Uncoded existing location fixture','item_type'=>'activity','experience_category'=>'dia-phuong',
        ])->assertCreated()->json('id');
        $this->putJson('/api/admin/attractions/'.$id,['location_id'=>999999])->assertUnprocessable()->assertJsonValidationErrors('location_id');
        $this->putJson('/api/admin/attractions/'.$id,['is_active'=>true,'verify'=>true,'publish'=>true,
            'experience_source_url'=>'https://example.test/source'])->assertUnprocessable()->assertJsonValidationErrors('verify');
        $this->putJson('/api/admin/attractions/'.$id,['location_id'=>72,'is_active'=>true,'publish'=>true])
            ->assertUnprocessable()->assertJsonValidationErrors('publish');
        $this->assertSame(2,Attraction::findOrFail($id)->location_id);
        $this->getJson('/api/experiences')->assertOk()->assertJsonCount(0,'data');
        $this->assertSame($locations,DB::table('location')->orderBy('id')->get()->toJson());
    }

    public function test_experience_crud_customer_filters_hide_unpublished_and_keep_same_location_hotels(): void
    {
        $this->admin();$hotels=Fixture::hotels();
        $beforeHotels=DB::table('hotel')->orderBy('id')->get()->toJson();
        $ids=[];
        foreach([[72,'CRUD Hanoi one'],[72,'CRUD Hanoi two'],[405,'CRUD Phu Quoc']] as [$location,$name]){
            $ids[]=$this->postJson('/api/admin/locations/'.$location.'/attractions',[
                'name'=>$name,'item_type'=>'attraction','experience_category'=>'tham-quan',
                'experience_source_url'=>'https://example.test/source','is_active'=>true,'verify'=>true,'publish'=>true,
            ])->assertCreated()->json('id');
        }
        $slug=Attraction::findOrFail($ids[0])->slug;
        $this->getJson('/api/experiences?location=vn-ha-noi&q=CRUD&per_page=1&page=2')->assertOk()
            ->assertJsonCount(1,'data')->assertJsonPath('meta.total',2)->assertJsonPath('meta.current_page',2);
        $this->getJson('/api/experiences?location=vn-phu-quoc&q=Hanoi')->assertOk()->assertJsonCount(0,'data');
        $this->getJson('/api/experiences/'.$slug)->assertOk()->assertJsonCount(1,'data.hotels')->assertJsonPath('data.hotels.0.id',$hotels[0])
            ->assertJsonPath('data.cover',null);
        $this->get('/trai-nghiem/'.$slug)->assertOk()->assertSee('Ảnh địa điểm đang được cập nhật');
        $this->putJson('/api/admin/attractions/'.$ids[0],['name'=>'Changed without approval'])->assertOk();
        $this->getJson('/api/experiences/'.$slug)->assertNotFound();
        $this->putJson('/api/admin/attractions/'.$ids[1],['publish'=>false])->assertOk();
        $this->getJson('/api/experiences/filters')->assertOk()->assertJsonCount(1,'locations')->assertJsonPath('locations.0.code','vn-phu-quoc');
        $this->assertSame($beforeHotels,DB::table('hotel')->orderBy('id')->get()->toJson());
    }

    public function test_experience_crud_hide_archive_restore_preserve_rows_and_images(): void
    {
        $this->admin();
        $item=$this->post('/api/admin/locations/72/attractions',[
            'name'=>'Archive CRUD fixture','description'=>'Content retained.','item_type'=>'attraction','experience_category'=>'tham-quan',
            'experience_source_url'=>'https://example.test/source','is_active'=>1,'verify'=>1,'publish'=>1,
            'image_source'=>'Synthetic runner asset.','images'=>[UploadedFile::fake()->image('retained.png',90,70)],
        ],['Accept'=>'application/json'])->assertCreated()->json();
        $path=preg_replace('~^storage/~','',DB::table('attraction_image')->where('attraction_id',$item['id'])->value('image_url'));
        $this->putJson('/api/admin/attractions/'.$item['id'].'/toggle')->assertOk()->assertJsonPath('is_active',false);
        $this->getJson('/api/experiences/'.$item['slug'])->assertNotFound();
        $this->putJson('/api/admin/attractions/'.$item['id'].'/toggle')->assertOk()->assertJsonPath('is_active',true);
        $this->getJson('/api/experiences/'.$item['slug'])->assertOk();
        $this->deleteJson('/api/admin/attractions/'.$item['id'])->assertOk();
        $this->getJson('/api/experiences/'.$item['slug'])->assertNotFound();
        $this->getJson('/api/admin/attractions?archived=1')->assertOk()->assertJsonCount(1,'data');
        $this->postJson('/api/admin/attractions/'.$item['id'].'/restore')->assertOk();
        $this->getJson('/api/admin/attractions/'.$item['id'])->assertOk()->assertJsonPath('is_active',false)
            ->assertJsonPath('experience_published_at',null)->assertJsonPath('experience_verified_at',null)
            ->assertJsonPath('description','Content retained.')->assertJsonCount(1,'images');
        $this->getJson('/api/experiences/'.$item['slug'])->assertNotFound();
        $this->assertSame(1,Attraction::count());$this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_experience_crud_non_admin_cannot_write(): void
    {
        $item=$this->experience();
        $writes=[['POST','/api/admin/locations/72/attractions'],['PUT','/api/admin/attractions/'.$item->id],
            ['DELETE','/api/admin/attractions/'.$item->id],['PUT','/api/admin/attractions/'.$item->id.'/toggle'],
            ['POST','/api/admin/attractions/'.$item->id.'/restore']];
        foreach($writes as [$method,$url])$this->json($method,$url)->assertUnauthorized();
        $this->withToken(Fixture::token('user','customer_token',['customer']));
        foreach($writes as [$method,$url])$this->json($method,$url)->assertForbidden();
        $this->assertNull($item->fresh()->experience_archived_at);
    }

    public function test_experience_crud_failed_uploads_are_atomic(): void
    {
        $item=$this->experience();$this->admin();$before=count(Storage::disk('public')->allFiles());
        $this->post('/api/admin/locations/72/attractions',[
            'name'=>'Rejected upload','item_type'=>'attraction','images'=>[UploadedFile::fake()->image('no-source.png',80,80)],
        ],['Accept'=>'application/json'])->assertUnprocessable()->assertJsonValidationErrors('image_source');
        $this->post('/api/admin/locations/72/attractions',[
            'name'=>'Rejected publication','item_type'=>'attraction','is_active'=>1,'verify'=>1,'publish'=>1,
            'image_source'=>'Synthetic runner asset.','images'=>[UploadedFile::fake()->image('rolled-back.png',80,80)],
        ],['Accept'=>'application/json'])->assertUnprocessable();
        $this->post('/api/admin/locations/72/attractions',[
            'name'=>'Rejected SVG','item_type'=>'attraction','image_source'=>'Synthetic runner asset.',
            'images'=>[UploadedFile::fake()->create('unsafe.svg',1,'image/svg+xml')],
        ],['Accept'=>'application/json'])->assertUnprocessable();
        $this->assertSame(1,Attraction::count());
        $this->assertCount($before,Storage::disk('public')->allFiles());
        $this->assertNull($item->fresh()->experience_archived_at);
    }

    public function test_owned_target_foreign_keys_unique_indexes_and_nullable_legacy_slugs(): void
    {
        $identity=Environment::assertTarget();$this->assertSame('8.0.30',$identity['version']);$this->assertSame(['catalog_test'],array_keys(config('database.connections')));
        $this->assertSame(Environment::settings()['database'],$identity['db']);$this->assertStringContainsString('STRICT_TRANS_TABLES',$identity['sql_mode']);
        $this->assertFalse(SchemaExistsOutsideScope::present());
        $this->databaseRejects(fn()=>DB::table('location')->insert(['name'=>'Duplicate code','code'=>'vn-ha-noi']),1062);
        $item=$this->experience();$this->databaseRejects(fn()=>DB::table('attraction')->insert(['location_id'=>999999,'name'=>'Invalid','item_type'=>'attraction']),1452);
        $this->databaseRejects(fn()=>DB::table('attraction')->insert(['location_id'=>72,'name'=>'Duplicate slug','item_type'=>'attraction','slug'=>$item->slug]),1062);
        $this->databaseRejects(fn()=>DB::table('attraction_image')->insert(['attraction_id'=>999999,'image_url'=>'missing.png']),1452);
        $post=Fixture::guide();$this->databaseRejects(fn()=>DB::table('guide_post')->insert(['title'=>'Duplicate','slug'=>$post->slug]),1062);
        $this->databaseRejects(fn()=>DB::table('guide_post')->insert(['title'=>'Invalid location','slug'=>'bad-location','location_id'=>999999]),1452);
        $this->databaseRejects(fn()=>DB::table('guide_post')->insert(['title'=>'Invalid category','slug'=>'bad-category','category_id'=>999999]),1452);
        $this->databaseRejects(fn()=>DB::table('guide_category')->insert(['name'=>'Duplicate slug','slug'=>$post->category->slug]),1062);
        foreach([1,2] as $n)DB::table('attraction')->insert(['location_id'=>72,'name'=>'Legacy nullable slug '.$n,'item_type'=>'activity','slug'=>null]);
        $this->assertSame(3,Attraction::whereNull('slug')->count());
        $this->expectException(\InvalidArgumentException::class);DB::connection('mysql');
    }

    public function test_seed_identity_and_nine_legacy_posts_are_preserved_and_rerunnable_on_mysql(): void
    {
        app(AttractionSeeder::class)->run();$this->assertSame(48,Attraction::count());
        $source=file_get_contents(database_path('seeders/AttractionSeeder.php'));
        preg_match_all("/'location_key'\s*=>\s*'([^']+)'[\s\S]*?'name'\s*=>\s*'([^']+)'/u",$source,$pairs,PREG_SET_ORDER);
        $this->assertCount(47,$pairs);$expected=['vn-ha-noi'=>72,'vn-ho-chi-minh'=>901,'vn-da-lat'=>18,'vn-phu-quoc'=>405];
        foreach($pairs as $pair){$item=Attraction::where('name',$pair[2])->firstOrFail();$this->assertSame($expected[$pair[1]],$item->location_id);$this->assertFalse($item->is_active);$this->assertNull($item->experience_published_at);}
        app(AttractionSeeder::class)->run();$this->assertSame(48,Attraction::count());$this->getJson('/api/experiences')->assertOk()->assertJsonCount(0,'data');
        $report=app(LegacyBlogImporter::class)->apply();$this->assertCount(9,$report);$this->assertSame(0,GuidePost::where('status','published')->count());$this->assertSame(9,GuidePost::where('status','draft')->count());
        foreach(LegacyBlogCatalog::INDEX as $metadata){
            $post=GuidePost::where('legacy_id',$metadata['id'])->firstOrFail();$this->assertEquals($metadata,$post->legacy_metadata['index']);$this->assertSame('image/'.$metadata['image'],$post->cover_path);
            $file=resource_path('views/content/blog-'.$metadata['id'].'-content.blade.php');$raw=is_file($file)?file_get_contents($file):null;
            $this->assertSame($raw,$post->legacy_body);$this->assertSame($raw===null?null:hash('sha256',$raw),$post->legacy_content_sha256);
            $this->assertNull($post->published_at);$this->assertFalse($post->is_featured);
            $this->get('/blog/'.$metadata['id'])->assertRedirect('/cam-nang?legacy='.$metadata['id']);
        }
        $one=GuidePost::where('legacy_id',1)->firstOrFail();$one->update(['title'=>'Biên tập synthetic không ghi đè']);app(LegacyBlogImporter::class)->apply();
        $this->assertSame(9,GuidePost::count());$this->assertSame('Biên tập synthetic không ghi đè',$one->fresh()->title);
        $this->getJson('/api/guides')->assertOk()->assertJsonCount(0,'data');$this->get('/blog')->assertStatus(301)->assertRedirect('/cam-nang');
    }

    public function test_mysql_filters_unicode_pagination_and_publication_gates_work_for_both_catalogs(): void
    {
        $first=$this->experience(['name'=>'Cà phê Hà Nội','item_type'=>'restaurant','experience_category'=>'am-thuc']);
        $this->experience(['name'=>'Ẩm thực Phú Quốc','location_id'=>405,'experience_location_code'=>'vn-phu-quoc','item_type'=>'restaurant','experience_category'=>'am-thuc']);
        $this->experience(['is_active'=>false]);$this->experience(['experience_verified_at'=>null]);$this->experience(['experience_location_code'=>'vn-da-lat']);
        $this->getJson('/api/experiences?location=vn-ha-noi&category=am-thuc&q='.urlencode('Cà phê'))->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$first->id);
        $this->getJson('/api/experiences?per_page=1&page=2')->assertOk()->assertJsonPath('meta.total',2)->assertJsonPath('meta.current_page',2);
        $this->getJson('/api/experiences?q=no-match')->assertOk()->assertJsonCount(0,'data');
        $guide=Fixture::guide(['title'=>'Cẩm nang Hà Nội','is_featured'=>true]);Fixture::guide(['title'=>'Ẩm thực Sài Gòn','location_id'=>901]);
        foreach([['status'=>'draft'],['archived_at'=>now()],['published_at'=>now()->addDay()]] as $override)Fixture::guide($override);
        $this->getJson('/api/guides?q='.urlencode('Hà Nội'))->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$guide->id);
        $this->getJson('/api/guides?per_page=1&page=2')->assertOk()->assertJsonPath('meta.total',2)->assertJsonPath('featured',null);
        $this->getJson('/api/guides?q=no-match')->assertOk()->assertJsonCount(0,'data');
        $this->get('/trai-nghiem/'.$first->slug)->assertOk()->assertSee('Cà phê Hà Nội');$this->get('/cam-nang/'.$guide->slug)->assertOk()->assertSee('Cẩm nang Hà Nội');
        $data=$this->getJson('/api/guides/'.$guide->slug)->assertOk()->json('data');$this->assertArrayNotHasKey('legacy_body',$data);$this->assertArrayNotHasKey('legacy_metadata',$data);
    }

    public function test_admin_changes_cross_module_suggestions_and_matching_hotels_immediately(): void
    {
        $this->admin();Fixture::hotels();
        $experience=$this->postJson('/api/admin/locations/72/attractions',['name'=>'Trải nghiệm tích hợp','item_type'=>'attraction','experience_category'=>'tham-quan','experience_source_url'=>'https://example.test/fixture'])->assertCreated()->json();
        $guide=$this->postJson('/api/admin/guides',$this->guideData())->assertCreated()->json();
        $this->getJson('/api/guides/'.$guide['slug'])->assertOk()->assertJsonCount(0,'data.experiences')->assertJsonCount(1,'data.hotels');
        $this->putJson('/api/admin/attractions/'.$experience['id'],['is_active'=>true,'verify'=>true,'publish'=>true])->assertOk();
        $this->getJson('/api/guides/'.$guide['slug'])->assertOk()->assertJsonPath('data.experiences.0.slug',$experience['slug'])->assertJsonPath('data.hotels.0.name','Khách sạn kiểm thử Hà Nội');
        $this->putJson('/api/admin/attractions/'.$experience['id'],['name'=>'Trải nghiệm đã biên tập'])->assertOk()->assertJsonPath('experience_verified_at',null);
        $this->getJson('/api/guides/'.$guide['slug'])->assertOk()->assertJsonCount(0,'data.experiences');
        $this->putJson('/api/admin/attractions/'.$experience['id'],['verify'=>true,'publish'=>true])->assertOk();
        $this->putJson('/api/admin/guides/'.$guide['id'],$this->guideData(['title'=>'Cẩm nang đã biên tập']))->assertOk()->assertJsonPath('slug',$guide['slug']);
        $this->getJson('/api/guides/'.$guide['slug'])->assertOk()->assertJsonPath('data.title','Cẩm nang đã biên tập')->assertJsonPath('data.experiences.0.name','Trải nghiệm đã biên tập');
        $this->getJson('/api/experiences/'.$experience['slug'])->assertOk()->assertJsonCount(1,'data.hotels');
        $this->putJson('/api/admin/guides/categories/'.$guide['category_id'],['name'=>'Danh mục tích hợp','is_active'=>false])->assertOk();
        $this->getJson('/api/guides/'.$guide['slug'])->assertNotFound();$this->getJson('/api/experiences/'.$experience['slug'])->assertOk();
        $categoryId=$this->postJson('/api/admin/guides/categories',['name'=>'MySQL category CRUD','is_active'=>true])->assertCreated()->json('id');
        $this->putJson('/api/admin/guides/categories/'.$categoryId,['name'=>'MySQL category edited','is_active'=>false])->assertOk();
        $this->deleteJson('/api/admin/guides/categories/'.$categoryId)->assertOk();
    }

    public function test_shared_real_storage_cover_selection_and_mysql_transaction_cleanup(): void
    {
        $this->admin();
        $experience=$this->post('/api/admin/locations/72/attractions',['name'=>'Gallery MySQL','item_type'=>'attraction','experience_category'=>'tham-quan',
            'experience_source_url'=>'https://example.test/source','is_active'=>1,'verify'=>1,'publish'=>1,'image_source'=>'Synthetic runner assets only',
            'images'=>[UploadedFile::fake()->image('one.png',60,40),UploadedFile::fake()->image('two.png',40,60)],'image_changes'=>json_encode(['primary_new'=>1])],['Accept'=>'application/json'])->assertCreated()->json();
        $this->assertCount(2,$experience['images']);$this->assertTrue($experience['images'][0]['is_primary']);
        $public=$this->getJson('/api/experiences/'.$experience['slug'])->assertOk()->json('data');$this->assertSame($experience['images'][0]['display_url'],$public['cover']['url']);
        $this->putJson('/api/admin/attractions/'.$experience['id'],['image_changes'=>['primary_id'=>$experience['images'][1]['id']],'verify'=>true,'publish'=>true])->assertOk();
        $this->getJson('/api/experiences/'.$experience['slug'])->assertOk()->assertJsonPath('data.cover.url',$experience['images'][1]['display_url']);
        $guide=$this->post('/api/admin/guides',[...$this->guideData(),'cover'=>UploadedFile::fake()->image('cover.png',70,50)],['Accept'=>'application/json'])->assertCreated()->json();
        $old=$guide['cover_path'];$this->assertStringStartsWith('storage/guides/',$old);Storage::disk('public')->assertExists(substr($old,8));
        $this->assertNotSame($public['cover']['url'],$guide['cover_url']);
        $this->post('/api/admin/guides/'.$guide['id'],[...$this->guideData(),'_method'=>'PUT','cover'=>UploadedFile::fake()->image('new.jpg',50,70)],['Accept'=>'application/json'])->assertOk();
        Storage::disk('public')->assertExists(substr($old,8));
        $files=Storage::disk('public')->allFiles();sort($files);$count=Attraction::count();
        $this->post('/api/admin/locations/72/attractions',['name'=>'Rollback gallery','item_type'=>'attraction','verify'=>1,'publish'=>1,'is_active'=>1,
            'images'=>[UploadedFile::fake()->image('rollback.png',50,50)],'image_source'=>'Synthetic failed transaction'],['Accept'=>'application/json'])->assertUnprocessable();
        $after=Storage::disk('public')->allFiles();sort($after);$this->assertSame($files,$after);$this->assertSame($count,Attraction::count());
    }

    public function test_both_admin_write_surfaces_reject_non_admin_tokens_on_mysql(): void
    {
        $experience=$this->experience();$guide=Fixture::guide();
        $writes=[['POST','/api/admin/locations/72/attractions'],['PUT','/api/admin/attractions/'.$experience->id],['DELETE','/api/admin/attractions/'.$experience->id],
            ['POST','/api/admin/attractions/'.$experience->id.'/restore'],['POST','/api/admin/guides'],['PUT','/api/admin/guides/'.$guide->id],
            ['DELETE','/api/admin/guides/'.$guide->id],['POST','/api/admin/guides/'.$guide->id.'/restore'],['POST','/api/admin/guides/categories'],['POST','/api/admin/guides/preview']];
        foreach($writes as [$method,$url])$this->json($method,$url)->assertUnauthorized();
        foreach([['user','admin_token',['admin']],['admin','customer_token',['admin']],['admin','admin_token',['read']]] as [$role,$name,$abilities]){
            $this->withToken(Fixture::token($role,$name,$abilities));foreach($writes as [$method,$url])$this->json($method,$url)->assertForbidden();
        }
        $this->assertNull($experience->fresh()->experience_archived_at);$this->assertNull($guide->fresh()->archived_at);
    }

    public function test_archive_restore_preserves_cross_module_records_images_and_publication_boundaries(): void
    {
        $this->admin();$experience=$this->experience();$guide=Fixture::guide();
        $this->deleteJson('/api/admin/attractions/'.$experience->id)->assertOk();$this->getJson('/api/guides/'.$guide->slug)->assertOk()->assertJsonCount(0,'data.experiences');
        $this->postJson('/api/admin/attractions/'.$experience->id.'/restore')->assertOk();$this->assertNull($experience->fresh()->experience_verified_at);$this->assertFalse($experience->fresh()->is_active);
        $this->deleteJson('/api/admin/guides/'.$guide->id)->assertOk();$this->getJson('/api/guides/'.$guide->slug)->assertNotFound();
        $this->postJson('/api/admin/guides/'.$guide->id.'/restore')->assertOk();$this->assertSame('draft',$guide->fresh()->status);$this->assertNull($guide->fresh()->archived_at);
    }

    public function test_future_publication_date_supported_in_sqlite_is_also_safe_on_mysql(): void
    {
        $this->admin();$id=$this->postJson('/api/admin/guides',$this->guideData(['published_at'=>'2099-01-02T07:30:00+07:00']))->assertCreated()->json('id');
        $post=GuidePost::findOrFail($id);$this->assertSame('2099-01-02 00:30:00',$post->published_at->format('Y-m-d H:i:s'));
        $this->getJson('/api/guides/'.$post->slug)->assertNotFound();
    }
}

final class SchemaExistsOutsideScope
{
    public static function present(): bool
    {
        $tables=\Illuminate\Support\Facades\Schema::getTableListing();
        return count(array_intersect($tables,['booking','bookings','payment','payments','itinerary','itineraries']))>0;
    }
}
