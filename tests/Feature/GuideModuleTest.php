<?php

namespace Tests\Feature;

use App\Models\GuideCategory;
use App\Models\GuidePost;
use App\Services\LegacyBlogImporter;
use App\Support\GuideHtml;
use App\Support\LegacyBlogCatalog;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ExperienceFixture;

final class GuideModuleTest extends TestCase
{
    public function createApplication() { return ExperienceFixture::application(getenv('AUREL_GUIDE_TEST_RUNTIME')); }
    protected function setUp(): void
    {
        parent::setUp(); ExperienceFixture::schema(); ExperienceFixture::locations();
        (require base_path('database/migrations/2026_10_23_000002_create_travel_guides.php'))->up();
        $this->withoutVite();
    }
    private function admin(): static { return $this->withToken(ExperienceFixture::token()); }
    private function category(): GuideCategory { return GuideCategory::firstOrCreate(['slug'=>'kiem-thu'], ['name'=>'Danh mục kiểm thử', 'is_active'=>true]); }
    private function data(array $override = []): array
    {
        return [...['title'=>'Cẩm nang kiểm thử cô lập', 'excerpt'=>'Tóm tắt kiểm thử, không dùng trên production.',
            'body_html'=>'<h2>Đề mục kiểm thử</h2><p>'.str_repeat('Nội dung kiểm thử có định dạng. ',10).'</p>',
            'category_id'=>$this->category()->id, 'author_name'=>'Tác giả kiểm thử', 'status'=>'published'], ...$override];
    }
    private function guide(array $override = []): GuidePost
    {
        static $number = 0; $number++;
        return GuidePost::create([...$this->data(), 'slug'=>'guide-fixture-'.$number, 'published_at'=>now()->subMinute(), ...$override]);
    }

    public function test_only_isolated_sqlite_is_configured_and_header_footer_use_guides_route(): void
    {
        $this->assertSame(['sqlite'], array_keys(config('database.connections')));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->get('/cam-nang')->assertOk()->assertSee('Đi để khám phá.')->assertSee('http://localhost/cam-nang',false)->assertDontSee('HolidayViet.')->assertDontSee('bannerblog.jpg');
        $this->get('/admin/guides')->assertOk()->assertSee('Viết bài mới')->assertSee('Cẩm nang');
        $this->expectException(\InvalidArgumentException::class); DB::connection('mysql');
    }

    public function test_import_preserves_nine_metadata_ids_assets_and_raw_files_without_executing_legacy_code(): void
    {
        $paths = [resource_path('views/blog.blade.php'),resource_path('views/blog_detail.blade.php'),...glob(resource_path('views/content/blog-*-content.blade.php')),
            ...array_map(fn($row)=>public_path('image/'.$row['image']),LegacyBlogCatalog::INDEX)];
        $before = array_map(fn($path)=>hash_file('sha256',$path),$paths);
        $report = app(LegacyBlogImporter::class)->apply();
        $this->assertCount(9,$report); $this->assertSame(9,GuidePost::count());
        $this->assertSame(0,GuidePost::where('status','published')->count()); $this->assertSame(9,GuidePost::where('status','draft')->count());
        foreach (LegacyBlogCatalog::INDEX as $meta) {
            $post=GuidePost::where('legacy_id',$meta['id'])->firstOrFail();
            $this->assertSame($meta,$post->legacy_metadata['index']); $this->assertSame('image/'.$meta['image'],$post->cover_path);
            $this->assertSame(collect(LegacyBlogCatalog::DETAIL)->firstWhere('id',$meta['id']),$post->legacy_metadata['detail']);
            $path=resource_path('views/content/blog-'.$meta['id'].'-content.blade.php');
            $this->assertSame(is_file($path)?file_get_contents($path):null,$post->legacy_body); $this->assertNull($post->location_id); $this->assertNull($post->author_name);
        }
        $one=GuidePost::where('legacy_id',1)->firstOrFail();
        $this->assertSame('complete',$one->legacy_import_state); $this->assertNull($one->published_at); $this->assertFalse($one->is_featured);
        $this->assertStringContainsString('Vịnh Vĩnh Hy',$one->body_html); $this->assertStringContainsString('15 - 30',$one->body_html);
        foreach (['<script','onclick','commentSection','Minh Nguyễn','Phát giọng đọc','Comment ngay'] as $bad) $this->assertStringNotContainsString($bad,$one->body_html);
        $this->assertSame('missing',GuidePost::where('legacy_id',3)->first()->legacy_import_state);
        $this->assertSame(7,GuidePost::where('legacy_import_state','empty')->count());
        $this->assertSame($before,array_map(fn($path)=>hash_file('sha256',$path),$paths));
    }

    public function test_import_is_idempotent_and_never_overwrites_admin_edits(): void
    {
        app(LegacyBlogImporter::class)->apply(); $post=GuidePost::where('legacy_id',1)->first();
        $post->update(['title'=>'Biên tập lại thật','status'=>'draft']);
        $report=app(LegacyBlogImporter::class)->apply();
        $this->assertSame(9,GuidePost::count()); $this->assertSame(5,GuideCategory::count());
        $this->assertSame('Biên tập lại thật',$post->fresh()->title); $this->assertSame('draft',$post->fresh()->status);
        $this->assertSame(['kept'],array_values(array_unique(array_column($report,'result'))));
    }

    public function test_command_default_is_filesystem_only_and_main_database_requires_explicit_flag(): void
    {
        config(['database.default'=>'unavailable-no-such-connection']);
        $this->artisan('guides:import-legacy')->expectsOutputToContain('Inspection only')->assertSuccessful();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>'hotel_booking']);
        $this->artisan('guides:import-legacy --apply')->expectsOutputToContain('Refusing hotel_booking')->assertFailed();
        $this->assertSame(0,GuidePost::count());
    }

    public function test_legacy_routes_redirect_published_posts_and_keep_all_known_drafts_away_from_404(): void
    {
        $this->get('/blog?q=pool')->assertStatus(301)->assertRedirect('/cam-nang?q=pool');
        for($id=1;$id<=9;$id++) $this->get('/blog/'.$id)->assertRedirect('/cam-nang?legacy='.$id);
        $this->guide(['slug'=>'pre-existing-guide']);
        app(LegacyBlogImporter::class)->apply();
        $one=GuidePost::where('legacy_id',1)->first();$this->assertNotSame($one->id,$one->legacy_id);
        $this->get('/blog/1')->assertRedirect('/cam-nang?legacy=1');
        // Simulate an explicit editorial decision in this isolated fixture only.
        $one->update(['status'=>'published','published_at'=>now()->subMinute()]);
        $this->get('/blog/1')->assertRedirect('/cam-nang/'.$one->slug);
        $this->get('/cam-nang/'.$one->slug)->assertOk()->assertSee(LegacyBlogCatalog::INDEX[0]['title'])->assertSee('Chưa có thông tin tác giả')->assertDontSee('Bình luận &amp; chia sẻ');
        for($id=2;$id<=9;$id++) { $this->get('/blog/'.$id)->assertRedirect('/cam-nang?legacy='.$id); $this->get('/cam-nang?legacy='.$id)->assertOk()->assertSee('đang được rà soát'); }
        $one->update(['status'=>'draft']);$this->get('/blog/1')->assertRedirect('/cam-nang?legacy=1');
        $this->get('/blog/999')->assertNotFound(); $this->get('/blog/not-an-id')->assertNotFound();
    }

    public function test_publication_filters_featured_pagination_and_private_field_boundaries(): void
    {
        $published=$this->guide(['is_featured'=>true]);
        foreach ([['status'=>'draft'],['published_at'=>now()->addDay()],['archived_at'=>now()],['body_html'=>null],['category_id'=>null]] as $override) {
            $hidden=$this->guide($override);$this->getJson('/api/guides/'.$hidden->slug)->assertNotFound();$this->get('/cam-nang/'.$hidden->slug)->assertNotFound();
        }
        $this->getJson('/api/guides')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('featured.id',$published->id)->assertJsonPath('meta.total',1);
        $this->getJson('/api/guides?q=no-match')->assertOk()->assertJsonCount(0,'data')->assertJsonPath('featured',null);
        $this->getJson('/api/guides?category=kiem-thu')->assertOk()->assertJsonCount(1,'data');
        $this->guide();$this->getJson('/api/guides?per_page=1&page=2')->assertOk()->assertJsonPath('meta.last_page',2);
        foreach (['page=-1','per_page=999','q[]=bad','category[]=bad'] as $query) $this->getJson('/api/guides?'.$query)->assertUnprocessable();
        $detail=$this->getJson('/api/guides/'.$published->slug)->assertOk()->json('data');
        foreach (['legacy_id','legacy_metadata','legacy_body','legacy_content_sha256','status','archived_at','cover_path','rating','views','comments'] as $key) $this->assertArrayNotHasKey($key,$detail);
        $this->category()->update(['is_active'=>false]);$this->getJson('/api/guides')->assertOk()->assertJsonCount(0,'data');
    }

    public function test_every_admin_write_requires_admin_role_token_name_and_ability(): void
    {
        $post=$this->guide();$category=$this->category();
        $writes=[['POST','/api/admin/guides'],['PUT','/api/admin/guides/'.$post->id],['DELETE','/api/admin/guides/'.$post->id],
            ['POST','/api/admin/guides/'.$post->id.'/restore'],['POST','/api/admin/guides/categories'],['PUT','/api/admin/guides/categories/'.$category->id],
            ['DELETE','/api/admin/guides/categories/'.$category->id],['POST','/api/admin/guides/preview']];
        foreach($writes as [$method,$url])$this->json($method,$url)->assertUnauthorized();
        foreach([['user','admin_token',['admin']],['admin','customer_token',['admin']],['admin','admin_token',['read']]] as [$role,$name,$abilities]){
            $this->withToken(ExperienceFixture::token($role,$name,$abilities));foreach($writes as [$method,$url])$this->json($method,$url)->assertForbidden();
        }
        $this->assertNull($post->fresh()->archived_at);$this->assertSame(1,GuideCategory::count());
    }

    public function test_admin_draft_publish_hide_archive_restore_and_immutable_slug_without_notifications(): void
    {
        Mail::fake();Notification::fake();$this->admin();
        $id=$this->postJson('/api/admin/guides',$this->data(['status'=>'draft','legacy_id'=>123,'slug'=>'injected','legacy_body'=>'bad']))->assertCreated()->assertJsonPath('status','draft')->json('id');
        $post=GuidePost::findOrFail($id);$slug=$post->slug;$this->assertNull($post->legacy_id);$this->getJson('/api/guides/'.$slug)->assertNotFound();
        $this->putJson('/api/admin/guides/'.$id,$this->data(['title'=>'Tiêu đề đầy đủ đã đổi','slug'=>'evil']))->assertOk()->assertJsonPath('slug',$slug);
        $this->get('/cam-nang/'.$slug)->assertOk()->assertSee('Tiêu đề đầy đủ đã đổi');
        $this->putJson('/api/admin/guides/'.$id,$this->data(['status'=>'draft']))->assertOk();$this->getJson('/api/guides/'.$slug)->assertNotFound();
        $this->deleteJson('/api/admin/guides/'.$id)->assertOk();$this->assertNotNull($post->fresh()->archived_at);
        $this->putJson('/api/admin/guides/'.$id,$this->data())->assertStatus(409);
        $this->postJson('/api/admin/guides/'.$id.'/restore')->assertOk();$this->assertSame('draft',$post->fresh()->status);$this->assertNull($post->fresh()->archived_at);
        Mail::assertNothingSent();Notification::assertNothingSent();
    }

    public function test_publication_validation_and_safe_formatted_html_in_preview_storage_and_public_output(): void
    {
        $this->admin();
        foreach([['body_html'=>'<script>alert(1)</script>'],['excerpt'=>''],['author_name'=>''],['category_id'=>null]] as $invalid) $this->postJson('/api/admin/guides',$this->data($invalid))->assertUnprocessable();
        $payload='<h2 onclick="alert(1)">Tiêu đề</h2><p><strong>Đậm</strong> <em>Nghiêng</em></p><script>window.pwned=1</script><svg onload="alert(1)"></svg><iframe srcdoc="x"></iframe><img src=x onerror=alert(1)><a href="jav&#x61;script:alert(1)">x</a><a href="https://example.test/source" target="_blank" style="x" onmouseover="alert(1)">Nguồn</a><form>FORM<input></form><table><tr><td colspan="2">Ô</td></tr></table>'.str_repeat('<p>Nội dung hợp lệ.</p>',12);
        $clean=app(GuideHtml::class)->clean($payload);$this->assertSame($clean,app(GuideHtml::class)->clean($clean));
        $this->postJson('/api/admin/guides/preview',['body_html'=>$payload])->assertOk()->assertJsonPath('body_html',$clean);
        $id=$this->postJson('/api/admin/guides',$this->data(['body_html'=>$payload]))->assertCreated()->json('id');
        $post=GuidePost::findOrFail($id);$this->assertSame($clean,$post->body_html);
        foreach(['<script','<svg','<iframe','<img','<form','onclick','onerror','onmouseover','javascript:','style=','target='] as $bad)$this->assertStringNotContainsString($bad,$clean);
        foreach(['<h2>','<strong>','<em>','https://example.test/source','colspan="2"'] as $good)$this->assertStringContainsString($good,$clean);
        $this->get('/cam-nang/'.$post->slug)->assertOk()->assertSee('<strong>Đậm</strong>',false)->assertDontSee('window.pwned=1',false);
        $post->update(['body_html'=>$payload]);$this->getJson('/api/guides/'.$post->slug)->assertOk()->assertJsonPath('data.body_html',$clean);
    }

    public function test_cover_upload_replace_remove_preserves_old_assets_and_rejects_executable_or_invalid_files(): void
    {
        $this->admin();Storage::fake('public');
        $response=$this->post('/api/admin/guides',[...$this->data(),'cover'=>UploadedFile::fake()->image('cover.png',80,60)],['Accept'=>'application/json'])->assertCreated();
        $id=$response->json('id');$path=GuidePost::find($id)->cover_path;$this->assertStringStartsWith('storage/guides/'.$id.'/',$path);Storage::disk('public')->assertExists(substr($path,8));
        $this->post('/api/admin/guides/'.$id,[...$this->data(),'_method'=>'PUT','cover'=>UploadedFile::fake()->image('replace.jpg',60,80)],['Accept'=>'application/json'])->assertOk();
        $newPath=GuidePost::find($id)->cover_path;$this->assertNotSame($path,$newPath);Storage::disk('public')->assertExists(substr($path,8));
        $this->putJson('/api/admin/guides/'.$id,$this->data(['remove_cover'=>true]))->assertOk()->assertJsonPath('cover_url',null);Storage::disk('public')->assertExists(substr($newPath,8));
        $this->post('/api/admin/guides',[...$this->data(),'cover'=>UploadedFile::fake()->createWithContent('evil.svg','<svg onload="alert(1)"></svg>')],['Accept'=>'application/json'])->assertUnprocessable();
        $this->post('/api/admin/guides',[...$this->data(),'cover'=>UploadedFile::fake()->createWithContent('evil.jpg','<?php echo 1;')],['Accept'=>'application/json'])->assertUnprocessable();
        $this->assertSame(1,GuidePost::count());
    }

    public function test_category_crud_can_hide_posts_but_cannot_delete_a_linked_category(): void
    {
        $this->admin();$post=$this->guide();
        $this->deleteJson('/api/admin/guides/categories/'.$post->category_id)->assertUnprocessable();
        $this->putJson('/api/admin/guides/categories/'.$post->category_id,['name'=>'Tên mới','is_active'=>false])->assertOk();
        $this->getJson('/api/guides/'.$post->slug)->assertNotFound();
        $this->putJson('/api/admin/guides/categories/'.$post->category_id,['name'=>'Tên mới','is_active'=>true])->assertOk();
        $this->getJson('/api/guides/'.$post->slug)->assertOk();
        $id=$this->postJson('/api/admin/guides/categories',['name'=>'Danh mục trống','is_active'=>true])->assertCreated()->json('id');
        $this->deleteJson('/api/admin/guides/categories/'.$id)->assertOk();$this->assertNull(GuideCategory::find($id));
        $this->postJson('/api/admin/guides/categories',['name'=>'','is_active'=>true])->assertUnprocessable();
        $this->postJson('/api/admin/guides/categories',['name'=>'Tên mới','is_active'=>true])->assertUnprocessable();
    }

    public function test_location_suggestions_require_real_matching_active_relations(): void
    {
        $post=$this->guide(['location_id'=>72]);ExperienceFixture::published();ExperienceFixture::published(['slug'=>'elsewhere','location_id'=>405,'experience_location_code'=>'vn-phu-quoc']);
        ExperienceFixture::published(['slug'=>'inactive','is_active'=>false]);
        DB::table('hotel')->insert([['location_id'=>72,'name'=>'Khách sạn cùng nơi','status'=>'active'],['location_id'=>405,'name'=>'Khác nơi','status'=>'active'],['location_id'=>72,'name'=>'Đã ngừng','status'=>'inactive']]);
        $this->getJson('/api/guides/'.$post->slug)->assertOk()->assertJsonCount(1,'data.experiences')->assertJsonCount(1,'data.hotels')->assertJsonPath('data.hotels.0.name','Khách sạn cùng nơi');
        $this->get('/cam-nang/'.$post->slug)->assertOk()->assertSee('Khám phá Hà Nội')->assertSee('Lưu trú tại Hà Nội');
        $post->update(['location_id'=>null]);$this->getJson('/api/guides/'.$post->slug)->assertOk()->assertJsonCount(0,'data.experiences')->assertJsonCount(0,'data.hotels');
    }

    public function test_schema_absent_has_recoverable_503_and_known_legacy_paths_still_redirect(): void
    {
        Schema::drop('guide_post');Schema::drop('guide_category');
        $this->get('/cam-nang')->assertOk()->assertSee('guide-unavailable');
        $this->getJson('/api/guides')->assertStatus(503)->assertJsonPath('code','GUIDES_NOT_READY');
        $this->get('/cam-nang/missing-schema')->assertStatus(503)->assertSee('đang được cập nhật');
        $this->get('/blog/3')->assertRedirect('/cam-nang?legacy=3');
    }

    public function test_empty_catalog_and_only_drafts_return_success_with_zero_results(): void
    {
        $this->getJson('/api/guides')->assertOk()->assertJsonCount(0,'data')->assertJsonPath('meta.total',0)->assertJsonPath('featured',null);
        $this->guide(['status'=>'draft']);
        $this->getJson('/api/guides')->assertOk()->assertJsonCount(0,'data')->assertJsonPath('meta.total',0);
    }

    public function test_admin_missing_schema_is_reported_before_validation_queries(): void
    {
        $this->admin();Schema::drop('guide_post');Schema::drop('guide_category');
        $this->getJson('/api/admin/guides/meta')->assertStatus(503)->assertJsonPath('code','GUIDES_NOT_READY');
        $this->postJson('/api/admin/guides',['title'=>'Draft','status'=>'draft','category_id'=>999])
            ->assertStatus(503)->assertJsonPath('code','GUIDES_NOT_READY');
        $this->postJson('/api/admin/guides/categories',['name'=>'New category','is_active'=>true])
            ->assertStatus(503)->assertJsonPath('code','GUIDES_NOT_READY');
    }

    public function test_connection_failure_remains_an_error_instead_of_an_empty_catalog(): void
    {
        Schema::shouldReceive('hasColumns')->once()->andThrow(new \Illuminate\Database\QueryException(
            'sqlite','select fixture',[],new \PDOException('Synthetic connection failure')));
        $this->getJson('/api/guides')->assertStatus(503)->assertJsonPath('code','GUIDES_UNAVAILABLE')
            ->assertJsonMissingPath('data')->assertDontSee('Synthetic connection failure');
    }

    public function test_publication_time_is_normalized_and_bad_optional_query_cannot_break_the_page(): void
    {
        $this->admin();
        $id=$this->postJson('/api/admin/guides',$this->data(['published_at'=>'2099-01-02T07:30:00+07:00']))->assertCreated()->json('id');
        $post=GuidePost::findOrFail($id);$this->assertSame('2099-01-02 00:30:00',$post->published_at->format('Y-m-d H:i:s'));
        $this->getJson('/api/guides/'.$post->slug)->assertNotFound();
        $this->get('/cam-nang?legacy[]=bad')->assertOk();
    }
}
