<?php

namespace Tests\Feature;

use App\Models\GuideCategory;
use App\Models\GuidePost;
use App\Services\LegacyBlogImporter;
use App\Support\GuideHtml;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CatalogMysqlEnvironment as Environment;
use Tests\Support\CatalogMysqlFixture as Fixture;

final class GuideCrudMysqlTest extends TestCase
{
    public function createApplication() { return Environment::application(); }
    protected function setUp(): void
    {
        parent::setUp();Environment::assertTarget();DB::beginTransaction();$this->withoutVite();
        foreach([[72,'Hà Nội'],[901,'Hồ Chí Minh'],[18,'Đà Lạt']] as [$id,$name])
            DB::table('location')->insert(['id'=>$id,'name'=>$name,'country'=>'Việt Nam']);
    }
    protected function tearDown(): void
    {
        try { Mail::assertNothingSent();Notification::assertNothingSent();Http::assertNothingSent(); }
        finally { while(DB::transactionLevel()>0)DB::rollBack();parent::tearDown(); }
    }
    private function admin(): void { $this->withToken(Fixture::token()); }
    public function test_guide_archive_restore_preserves_content_assets_and_returns_to_draft(): void
    {
        $this->admin();$data=$this->input(['status'=>'published','published_at'=>'2026-01-02T03:04:37Z']);
        $post=$this->post('/api/admin/guides',[...$data,'cover'=>UploadedFile::fake()->image('archive.jpg',100,80)],['Accept'=>'application/json'])->assertCreated()->json();
        $count=GuidePost::count();$file=Storage::disk('public')->path(substr($post['cover_path'],8));$hash=hash_file('sha256',$file);
        $this->deleteJson('/api/admin/guides/'.$post['id'])->assertOk();
        $this->getJson('/api/admin/guides?archived=1')->assertOk()->assertJsonPath('data.0.id',$post['id']);
        $this->getJson('/api/admin/guides?archived=0')->assertOk()->assertJsonPath('meta.total',0);
        $this->getJson('/api/guides/'.$post['slug'])->assertNotFound();
        $this->postJson('/api/admin/guides/'.$post['id'].'/restore')->assertOk();
        $restored=$this->getJson('/api/admin/guides/'.$post['id'])->assertOk()->json();
        foreach(['id','slug','body_html','cover_path','published_at','title','excerpt','category_id','location_id','author_name'] as $key)$this->assertSame($post[$key],$restored[$key]);
        $this->assertNull($restored['archived_at']);$this->assertSame('draft',$restored['status']);$this->assertSame($count,GuidePost::count());$this->assertSame($hash,hash_file('sha256',$file));
        $this->getJson('/api/guides/'.$post['slug'])->assertNotFound();
    }
    public function test_guide_publication_time_offset_seconds_and_clearing_survive_mysql(): void
    {
        $this->admin();$data=$this->input(['published_at'=>'2039-03-02T00:04:37+07:00']);
        $post=$this->postJson('/api/admin/guides',$data)->assertCreated()->json();
        $this->assertSame('2039-03-01 17:04:37',GuidePost::findOrFail($post['id'])->getRawOriginal('published_at'));
        $saved=$this->putJson('/api/admin/guides/'.$post['id'],[...$data,'published_at'=>$post['published_at'],'excerpt'=>'Edited without changing the original timestamp.'])->assertOk()->json();
        $this->assertSame($post['published_at'],$saved['published_at']);
        $this->putJson('/api/admin/guides/'.$post['id'],[...$data,'published_at'=>null])->assertOk()->assertJsonPath('published_at',null);
        $this->getJson('/api/guides')->assertOk()->assertJsonPath('meta.total',0);
    }
    private function input(array $changes=[]): array
    {
        return [...['title'=>'Synthetic Guide CRUD','excerpt'=>'Synthetic integration excerpt.',
            'body_html'=>'<h2>Heading</h2><p>'.str_repeat('Synthetic safe editorial content. ',8).'</p>',
            'category_id'=>GuideCategory::firstOrCreate(['slug'=>'crud'],['name'=>'CRUD category','is_active'=>true])->id,
            'location_id'=>72,'author_name'=>'Synthetic test author','status'=>'draft'],...$changes];
    }
    public function test_only_guides_migrations_empty_and_drafts_are_not_errors(): void
    {
        $id=Environment::assertTarget();$this->assertSame('8.0.30',$id['version']);
        $this->assertSame(['catalog_test'],array_keys(config('database.connections')));
        $this->assertFalse(Schema::hasColumn('location','code'));$this->assertFalse(Schema::hasTable('attraction_image'));
        $this->get('/cam-nang')->assertOk();
        $this->getJson('/api/guides')->assertOk()->assertJsonCount(0,'data')->assertJsonPath('meta.total',0);
        $this->admin();$post=$this->postJson('/api/admin/guides',$this->input())->assertCreated()->json();
        $this->getJson('/api/guides')->assertOk()->assertJsonCount(0,'data')->assertJsonPath('meta.total',0);
        $this->getJson('/api/guides/'.$post['slug'])->assertNotFound();
    }
    public function test_admin_upload_publish_edit_replace_hide_and_restore_reach_customer(): void
    {
        $this->admin();
        $created=$this->post('/api/admin/guides',[...$this->input(),
            'cover'=>UploadedFile::fake()->image('first.jpg',90,70)],['Accept'=>'application/json'])->assertCreated()->json();
        $original=$created['cover_path'];$this->assertTrue(Storage::disk('public')->exists(substr($original,8)));
        $this->getJson('/api/guides/'.$created['slug'])->assertNotFound();
        $published=$this->putJson('/api/admin/guides/'.$created['id'],$this->input(['status'=>'published']))->assertOk()->json();
        $this->getJson('/api/guides')->assertOk()->assertJsonPath('data.0.title','Synthetic Guide CRUD');
        $this->get('/cam-nang/'.$created['slug'])->assertOk()->assertSee('Heading');
        $updated=$this->post('/api/admin/guides/'.$created['id'],[...$this->input(['title'=>'Edited Guide CRUD','status'=>'published']),
            '_method'=>'PUT','cover'=>UploadedFile::fake()->image('replacement.jpg',110,75)],['Accept'=>'application/json'])->assertOk()->json();
        $this->assertSame($created['slug'],$updated['slug']);$this->assertNotSame($original,$updated['cover_path']);
        $this->assertTrue(Storage::disk('public')->exists(substr($original,8)));
        $this->getJson('/api/guides/'.$created['slug'])->assertOk()->assertJsonPath('data.title','Edited Guide CRUD')
            ->assertJsonPath('data.cover_url',$updated['cover_url'])->assertJsonCount(0,'data.experiences');
        $this->putJson('/api/admin/guides/'.$created['id'],$this->input(['status'=>'draft']))->assertOk();
        $this->getJson('/api/guides/'.$created['slug'])->assertNotFound();
        $this->putJson('/api/admin/guides/'.$created['id'],$this->input(['status'=>'published','remove_cover'=>true]))->assertOk();
        $this->getJson('/api/guides/'.$created['slug'])->assertOk()->assertJsonPath('data.cover_url',null);
        $this->deleteJson('/api/admin/guides/'.$created['id'])->assertOk();
        $this->postJson('/api/admin/guides/'.$created['id'].'/restore')->assertOk();
        $this->assertSame('draft',GuidePost::findOrFail($created['id'])->status);
    }
    public function test_search_category_pagination_and_hidden_categories_use_saved_mysql_data(): void
    {
        $this->admin();$data=$this->input(['status'=>'published']);
        foreach(range(1,11) as $n)$this->postJson('/api/admin/guides',[...$data,'title'=>'Travel fixture '.$n])->assertCreated();
        $this->getJson('/api/guides?per_page=3&page=2')->assertOk()->assertJsonCount(3,'data')->assertJsonPath('meta.total',11)->assertJsonPath('meta.last_page',4);
        $this->getJson('/api/guides?q=Travel%20fixture%2011&category=crud')->assertOk()->assertJsonCount(1,'data');
        $this->getJson('/api/guides?q=nonexistent')->assertOk()->assertJsonCount(0,'data')->assertJsonPath('meta.total',0);
        $this->putJson('/api/admin/guides/categories/'.$data['category_id'],['name'=>'CRUD category','is_active'=>false])->assertOk();
        $this->getJson('/api/guides')->assertOk()->assertJsonPath('meta.total',0);
    }
    public function test_publication_validation_and_html_sanitization(): void
    {
        $this->admin();$this->postJson('/api/admin/guides',$this->input(['status'=>'published','body_html'=>'<script>alert(1)</script>']))
            ->assertUnprocessable()->assertJsonValidationErrors('body_html');
        $this->postJson('/api/admin/guides',$this->input(['status'=>'published','author_name'=>null]))->assertUnprocessable();
        $malicious='<script>window.guidePwned=1</script><p onclick="alert(1)"><strong>Safe heading.</strong> '.str_repeat('Safe content. ',20).'</p>';
        $post=$this->postJson('/api/admin/guides',$this->input(['status'=>'published','body_html'=>$malicious]))->assertCreated()->json();
        $this->getJson('/api/guides/'.$post['slug'])->assertOk()->assertDontSee('window.guidePwned')->assertDontSee('onclick')->assertSee('Safe heading.');
    }
    public function test_three_real_content_drafts_and_nine_legacy_posts_stay_unpublished(): void
    {
        $this->admin();$manifest=json_decode(file_get_contents(resource_path('content/guides/drafts.json')),true,512,JSON_THROW_ON_ERROR);
        $category=$this->postJson('/api/admin/guides/categories',['name'=>$manifest['category_name'],'is_active'=>true])->assertCreated()->json();
        $this->assertCount(3,$manifest['records']);
        foreach($manifest['records'] as $draft){
            $location=DB::table('location')->where('name',$draft['location_name'])->where('country',$draft['country'])->sole();
            $body=file_get_contents(resource_path('content/guides/'.$draft['body_file']));
            $this->assertGreaterThan(100,mb_strlen(app(GuideHtml::class)->text($body)));
            $cover=resource_path('content/guides/'.$draft['cover_file']);$this->assertFileExists($cover);
            $post=$this->post('/api/admin/guides',['title'=>$draft['title'],'excerpt'=>$draft['excerpt'],'body_html'=>$body,
                'category_id'=>$category['id'],'location_id'=>$location->id,'author_name'=>null,'status'=>'draft',
                'cover'=>new UploadedFile($cover,basename($cover),'image/jpeg',null,true)],['Accept'=>'application/json'])->assertCreated()->json();
            $this->assertSame('draft',$post['status']);$this->assertNull($post['published_at']);$this->assertNull($post['author_name']);
            $this->assertStringContainsString('https://commons.wikimedia.org/',$post['body_html']);
            $this->assertStringContainsString('Ảnh bìa:',$post['body_html']);
            $this->getJson('/api/guides/'.$post['slug'])->assertNotFound();
        }
        $report=app(LegacyBlogImporter::class)->apply();$this->assertCount(9,$report);
        $this->assertSame(12,GuidePost::count());$this->assertSame(0,GuidePost::where('status','published')->count());
        $this->getJson('/api/guides')->assertOk()->assertJsonPath('meta.total',0);
    }
    public function test_missing_schema_returns_explicit_state_before_admin_exists_validation(): void
    {
        DB::rollBack();$this->admin();Environment::assertTarget();Schema::rename('guide_post','_guide_crud_hidden_post');
        try{
            $this->getJson('/api/guides')->assertStatus(503)->assertJsonPath('code','GUIDES_NOT_READY');
            $this->getJson('/api/admin/guides/meta')->assertStatus(503)->assertJsonPath('code','GUIDES_NOT_READY');
            $this->postJson('/api/admin/guides',['title'=>'Draft','status'=>'draft','category_id'=>999])->assertStatus(503)->assertJsonPath('code','GUIDES_NOT_READY');
            $this->postJson('/api/admin/guides/categories',['name'=>'Missing schema','is_active'=>true])->assertStatus(503)->assertJsonPath('code','GUIDES_NOT_READY');
        }finally{Environment::assertTarget();Schema::rename('_guide_crud_hidden_post','guide_post');}
    }
    public function test_unauthenticated_writes_are_rejected(): void
    {
        $this->postJson('/api/admin/guides',['title'=>'Denied','status'=>'draft'])->assertUnauthorized();
        $this->postJson('/api/admin/guides/categories',['name'=>'Denied','is_active'=>true])->assertUnauthorized();
        $this->deleteJson('/api/admin/guides/1')->assertUnauthorized();
    }
}
