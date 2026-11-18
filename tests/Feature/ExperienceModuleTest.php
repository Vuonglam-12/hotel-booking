<?php

namespace Tests\Feature;

use App\Models\Attraction;
use App\Models\Location;
use App\Support\LocationIdentity;
use Database\Seeders\AttractionSeeder;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ExperienceFixture;

final class ExperienceModuleTest extends TestCase
{
    public function createApplication()
    {
        return ExperienceFixture::application(getenv('AUREL_EXPERIENCE_TEST_RUNTIME'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        ExperienceFixture::schema(); ExperienceFixture::locations(); $this->withoutVite();
    }

    private function admin(): static
    {
        return $this->withToken(ExperienceFixture::token());
    }

    public function test_only_the_in_memory_connection_is_available_and_header_uses_the_real_route(): void
    {
        $this->assertSame(['sqlite'], array_keys(config('database.connections')));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->get('/trai-nghiem')->assertOk()->assertSee('Một điểm đến.')->assertSee('http://localhost/trai-nghiem', false);
        $this->expectException(\InvalidArgumentException::class);
        DB::connection('mysql');
    }

    public function test_public_reads_require_active_reviewed_published_and_correct_location_records(): void
    {
        ExperienceFixture::published();
        foreach ([['is_active' => false], ['experience_verified_at' => null], ['experience_published_at' => null],
            ['experience_location_code' => 'vn-da-lat'], ['experience_archived_at' => now()], ['experience_published_at' => now()->addDay()],
            ['item_type' => 'hotel']] as $index => $override) {
            ExperienceFixture::published([...$override, 'slug' => 'hidden-'.$index]);
            $this->getJson('/api/experiences/hidden-'.$index)->assertNotFound();
        }
        $this->getJson('/api/experiences')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/experiences/filters')->assertOk()->assertJsonCount(1, 'locations')->assertJsonPath('locations.0.code', 'vn-ha-noi');
        $this->get('/trai-nghiem/hidden-0')->assertNotFound();
    }

    public function test_public_fields_do_not_include_estimated_prices_ratings_or_default_ai_duration(): void
    {
        ExperienceFixture::published(['price_min' => 100000, 'price_max' => 999999, 'duration_hours' => 2]);
        $data = $this->getJson('/api/experiences/dia-diem-kiem-thu')->assertOk()->json('data');
        foreach (['price_min','price_max','rating','avg_rating','experience_verified_by','experience_verified_at'] as $key) $this->assertArrayNotHasKey($key, $data);
        $this->assertNull($data['duration_minutes']); $this->assertNull($data['cover']);
        $this->get('/trai-nghiem/dia-diem-kiem-thu')->assertOk()->assertSee('Ảnh địa điểm đang được cập nhật')->assertDontSee('100000');
    }

    public function test_six_legacy_hotels_never_enter_customer_search_and_do_not_hide_real_hotels(): void
    {
        $this->assertSame(['sqlite'],array_keys(config('database.connections')));
        $this->assertSame(':memory:',DB::connection()->getDatabaseName());
        // Adversarial metadata in a disposable fixture: type alone must still exclude these six legacy records.
        $legacy=[7=>'Hanoi Old Quarter Budget Hotel',8=>'Tirant Hotel Hanoi',16=>'Saigonciti Hotel',
            17=>'Liberty Central Saigon Centre',25=>'Tigold Hostel Da Lat',26=>'Hôtel Colline Đà Lạt'];
        $categories=['tham-quan','am-thuc','vui-choi','dia-phuong'];
        foreach($legacy as $id=>$name){
            $item=new Attraction(['location_id'=>72,'name'=>$name,'item_type'=>'hotel','slug'=>'legacy-hotel-'.$id,
                'experience_category'=>$categories[$id%4],'experience_summary'=>'legacy-search-sentinel',
                'experience_source_url'=>'https://example.test/isolated-legacy-fixture','is_active'=>true,
                'experience_location_code'=>'vn-ha-noi','experience_verified_by'=>501,
                'experience_verified_at'=>now()->subMinute(),'experience_published_at'=>now()->subMinute()]);
            $item->id=$id;$item->save();
        }
        // Same numeric ID in hotel is unrelated to attraction.id and must remain eligible for hotel suggestions.
        DB::table('hotel')->insert(['id'=>7,'location_id'=>72,'name'=>'Real hotel table control','status'=>'active']);
        $legacyBefore=DB::table('attraction')->orderBy('id')->get()->toJson();
        $hotelsBefore=DB::table('hotel')->orderBy('id')->get()->toJson();
        $this->getJson('/api/experiences')->assertOk()->assertJsonCount(0,'data')->assertJsonPath('meta.total',0);
        $this->getJson('/api/experiences?q=legacy-search-sentinel')->assertOk()->assertJsonCount(0,'data');
        $this->getJson('/api/experiences/filters')->assertOk()->assertJsonCount(0,'locations');
        foreach($categories as $category){
            $this->getJson('/api/experiences?location=vn-ha-noi&category='.$category.'&q=legacy-search-sentinel')
                ->assertOk()->assertJsonCount(0,'data');
        }
        foreach($legacy as $id=>$name){
            $this->getJson('/api/experiences?'.http_build_query(['q'=>$name]))->assertOk()->assertJsonCount(0,'data');
            $this->getJson('/api/experiences/legacy-hotel-'.$id)->assertNotFound();
            $this->get('/trai-nghiem/legacy-hotel-'.$id)->assertNotFound();
        }
        $this->assertSame($legacyBefore,DB::table('attraction')->orderBy('id')->get()->toJson());
        $control=ExperienceFixture::published(['slug'=>'public-experience-control','experience_summary'=>'legacy-search-sentinel']);
        $this->getJson('/api/experiences?q=legacy-search-sentinel')->assertOk()->assertJsonCount(1,'data')
            ->assertJsonPath('data.0.id',$control->id);
        $this->getJson('/api/experiences/'.$control->slug)->assertOk()->assertJsonCount(1,'data.hotels')
            ->assertJsonPath('data.hotels.0.id',7)->assertJsonPath('data.hotels.0.name','Real hotel table control');
        $this->assertSame($hotelsBefore,DB::table('hotel')->orderBy('id')->get()->toJson());
        $this->assertSame(6,Attraction::where('item_type','hotel')->count());
    }

    public function test_filters_pagination_validation_and_hotel_suggestions_use_actual_relations(): void
    {
        ExperienceFixture::published();
        ExperienceFixture::published(['slug' => 'mon-an', 'name' => 'Món ăn kiểm thử', 'item_type' => 'restaurant', 'experience_category' => 'am-thuc']);
        $this->getJson('/api/experiences?category=am-thuc&location=vn-ha-noi')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.slug','mon-an');
        $this->getJson('/api/experiences?per_page=1&page=2')->assertOk()->assertJsonPath('meta.current_page',2)->assertJsonPath('meta.last_page',2);
        foreach (['category=invalid','per_page=10000','page=-1','q[]=bad','sort=price'] as $query) $this->getJson('/api/experiences?'.$query)->assertUnprocessable();
        DB::table('hotel')->insert([
            ['location_id'=>72,'name'=>'Same location','status'=>'active'], ['location_id'=>405,'name'=>'Other location','status'=>'active'],
            ['location_id'=>72,'name'=>'Inactive hotel','status'=>'inactive'],
        ]);
        $this->getJson('/api/experiences/dia-diem-kiem-thu')->assertOk()->assertJsonCount(1,'data.hotels')->assertJsonPath('data.hotels.0.name','Same location');
    }

    public function test_every_admin_write_requires_the_admin_role_token_name_and_ability(): void
    {
        $item = ExperienceFixture::published();
        $writes = [['POST','/api/admin/locations/72/attractions'],['PUT','/api/admin/attractions/'.$item->id],
            ['PUT','/api/admin/attractions/'.$item->id.'/toggle'],['DELETE','/api/admin/attractions/'.$item->id],
            ['POST','/api/admin/attractions/'.$item->id.'/restore'],['PUT','/api/admin/locations/72']];
        foreach ($writes as [$method,$url]) $this->json($method,$url)->assertUnauthorized();
        foreach ([['user','admin_token',['admin']],['admin','customer_token',['admin']],['admin','admin_token',['read']]] as [$role,$name,$abilities]) {
            $this->withToken(ExperienceFixture::token($role,$name,$abilities));
            foreach ($writes as [$method,$url]) $this->json($method,$url)->assertForbidden();
        }
        $this->assertNull($item->fresh()->experience_archived_at);
    }

    public function test_admin_creates_drafts_then_explicitly_verifies_and_publishes(): void
    {
        $this->admin();
        $id = $this->postJson('/api/admin/locations/72/attractions', ['name'=>'Nơi mới','item_type'=>'attraction','experience_category'=>'tham-quan',
            'experience_verified_at'=>now()->toDateTimeString(),'experience_published_at'=>now()->toDateTimeString(),'experience_verified_by'=>1])
            ->assertCreated()->assertJsonPath('is_active',false)->json('id');
        $this->assertNull(Attraction::findOrFail($id)->experience_verified_at);
        $slug = Attraction::findOrFail($id)->slug;
        $this->getJson('/api/experiences/'.$slug)->assertNotFound();
        $this->putJson('/api/admin/attractions/'.$id, ['is_active'=>true,'publish'=>true])->assertUnprocessable();
        $this->putJson('/api/admin/attractions/'.$id, ['is_active'=>true,'verify'=>true,'publish'=>true,'experience_source_url'=>'https://example.test/source','experience_duration_minutes'=>90])
            ->assertOk()->assertJsonPath('experience_location_code','vn-ha-noi');
        $this->getJson('/api/experiences/'.$slug)->assertOk()->assertJsonPath('data.duration_minutes',90);
        $this->putJson('/api/admin/attractions/'.$id,['name'=>'Tên đã đổi'])->assertOk();
        $this->assertSame($slug, Attraction::findOrFail($id)->slug);
        $this->getJson('/api/experiences/'.$slug)->assertNotFound();
        $this->putJson('/api/admin/attractions/'.$id,['slug'=>'new-url'])->assertUnprocessable();
    }

    public function test_location_codes_are_unique_immutable_and_required_for_publication(): void
    {
        Location::whereKey(72)->update(['code'=>null]); $this->admin();
        $id = $this->postJson('/api/admin/locations/72/attractions',['name'=>'Draft','item_type'=>'attraction','experience_category'=>'tham-quan'])->assertCreated()->json('id');
        $this->putJson('/api/admin/attractions/'.$id,['verify'=>true,'experience_source_url'=>'https://example.test/source'])->assertUnprocessable();
        $this->putJson('/api/admin/locations/72',['code'=>'vn-da-lat'])->assertUnprocessable();
        $this->putJson('/api/admin/locations/72',['code'=>'vn-ha-noi'])->assertOk()->assertJsonPath('code','vn-ha-noi');
        $this->putJson('/api/admin/locations/72',['code'=>'different-place'])->assertUnprocessable();
    }

    public function test_validation_rejects_unsafe_input_and_keeps_legacy_fields_compatible(): void
    {
        $this->admin();
        foreach ([['name'=>'','item_type'=>'attraction'],['name'=>'x','item_type'=>'invalid'],
            ['name'=>'x','item_type'=>'attraction','experience_source_url'=>'javascript:alert(1)'],
            ['name'=>'x','item_type'=>'attraction','lat'=>100],['name'=>'x','item_type'=>'attraction','open_time'=>'never'],
            ['name'=>'x','item_type'=>'attraction','experience_duration_minutes'=>0]] as $payload) {
            $this->postJson('/api/admin/locations/72/attractions',$payload)->assertUnprocessable();
        }
        $this->assertSame(0,Attraction::count());
        $id = $this->postJson('/api/admin/locations/72/attractions',['name'=>'Legacy','item_type'=>'hotel','price_min'=>100,'price_max'=>200,'duration_hours'=>12])->assertCreated()->json('id');
        $this->assertSame(100,(int)Attraction::findOrFail($id)->price_min);
        $this->putJson('/api/admin/attractions/'.$id,['experience_category'=>'tham-quan','verify'=>true,'experience_source_url'=>'https://example.test/source'])->assertUnprocessable();
    }

    public function test_gallery_upload_is_owned_sourced_and_atomic_with_publication(): void
    {
        $this->admin();
        $this->post('/api/admin/locations/72/attractions',['name'=>'Missing image source','item_type'=>'attraction',
            'images'=>[UploadedFile::fake()->image('no-source.jpg',100,70)]],['Accept'=>'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('image_source');
        $payload = ['name'=>'Ảnh kiểm thử','item_type'=>'attraction','experience_category'=>'tham-quan',
            'image_source'=>'Ảnh nhân tạo chỉ trong kiểm thử cô lập','images'=>[UploadedFile::fake()->image('fixture.jpg',100,70)],
            'verify'=>true,'publish'=>true,'is_active'=>true,'experience_source_url'=>'https://example.test/source'];
        $created = $this->post('/api/admin/locations/72/attractions',$payload,['Accept'=>'application/json'])->assertCreated()->json();
        $this->assertCount(1,$created['images']);
        $this->putJson('/api/admin/attractions/'.$created['id'], ['image_source'=>null,'image_caption'=>null])->assertOk();
        $this->getJson('/api/experiences/'.$created['slug'])->assertOk()->assertJsonCount(1,'data.gallery');
        $foreign = ExperienceFixture::published(['slug'=>'other-record']);
        $this->putJson('/api/admin/attractions/'.$foreign->id,['image_changes'=>['delete_ids'=>[$created['images'][0]['id']]]])->assertUnprocessable();
        $this->assertSame(1,DB::table('attraction_image')->count());
        $before = count(Storage::disk('public')->allFiles());
        $payload['name']='Must rollback'; $payload['images']=[UploadedFile::fake()->image('rollback.png',100,100)]; unset($payload['experience_source_url']);
        $this->post('/api/admin/locations/72/attractions',$payload,['Accept'=>'application/json'])->assertUnprocessable();
        $this->assertFalse(Attraction::where('name','Must rollback')->exists());
        $this->assertCount($before,Storage::disk('public')->allFiles());
        $this->post('/api/admin/locations/72/attractions',['name'=>'Bad upload','item_type'=>'attraction','image_source'=>'fixture','images'=>[UploadedFile::fake()->create('bad.svg',1,'image/svg+xml')]],['Accept'=>'application/json'])->assertUnprocessable();
    }

    public function test_archive_restore_and_toggle_keep_shared_rows_and_estimates(): void
    {
        $item = ExperienceFixture::published(['price_min'=>100]); $this->admin();
        $this->putJson('/api/admin/attractions/'.$item->id.'/toggle')->assertOk()->assertJsonPath('is_active',false);
        $this->getJson('/api/experiences/'.$item->slug)->assertNotFound();
        $this->deleteJson('/api/admin/attractions/'.$item->id)->assertOk();
        $this->assertSame(100,(int)$item->fresh()->price_min);
        $this->getJson('/api/admin/attractions?archived=1')->assertOk()->assertJsonCount(1,'data');
        $this->postJson('/api/admin/attractions/'.$item->id.'/restore')->assertOk();
        $this->assertNull($item->fresh()->experience_verified_at);
    }

    public function test_seeder_resolves_shuffled_ids_without_publishing_or_overwriting_data(): void
    {
        $seeder = new AttractionSeeder(); $seeder->run();
        $this->assertSame(47,Attraction::count());
        $this->assertSame(72,(int)Attraction::where('name','Lăng Chủ tịch Hồ Chí Minh')->value('location_id'));
        $this->assertSame(901,(int)Attraction::where('name','Dinh Độc Lập')->value('location_id'));
        $this->assertSame(18,(int)Attraction::where('name','Khu Du lịch Langbiang')->value('location_id'));
        $this->assertSame(405,(int)Attraction::where('name','Bãi Sao')->value('location_id'));
        $this->assertSame(0,Attraction::where('is_active',true)->count());
        $legacy = Attraction::first(); $legacy->update(['description'=>'Retain this existing value']); $seeder->run();
        $this->assertSame('Retain this existing value',$legacy->fresh()->description);
        $this->assertSame(47,Attraction::count()); $this->getJson('/api/experiences')->assertJsonCount(0,'data');
    }

    public function test_identity_fallback_fails_on_ambiguous_names_before_any_seed_write(): void
    {
        Location::whereKey(72)->update(['code'=>null]);
        $this->assertSame(72,(int)LocationIdentity::resolveSeedKey('vn-ha-noi')->id);
        Location::create(['name'=>'Hà Nội','country'=>'Việt Nam']);
        try { (new AttractionSeeder())->run(); $this->fail('Ambiguous locations must fail'); }
        catch (\RuntimeException $error) { $this->assertStringContainsString('ambiguous',$error->getMessage()); }
        $this->assertSame(0,Attraction::count());
    }

    public function test_sample_seeder_refuses_the_primary_database_name_before_any_write(): void
    {
        // Only change the SQLite configuration string; no MySQL connection is configured or opened.
        config(['database.connections.sqlite.database'=>'hotel_booking']);
        try { (new AttractionSeeder())->run(); $this->fail('Primary database name must be refused'); }
        catch (\RuntimeException $error) { $this->assertStringContainsString('isolated',$error->getMessage()); }
        finally { config(['database.connections.sqlite.database'=>':memory:']); }
        $this->assertSame(0,Attraction::count());
    }

    public function test_unmigrated_schema_is_unavailable_without_touching_legacy_rows(): void
    {
        $legacy = Attraction::create(['location_id'=>72,'name'=>'Existing legacy data','item_type'=>'attraction','is_active'=>true]);
        $before = $legacy->getAttributes();
        (require base_path('database/migrations/2026_10_23_000001_add_experience_catalog_metadata.php'))->down();
        $this->getJson('/api/experiences')->assertStatus(503);
        $this->get('/trai-nghiem')->assertOk();
        $this->get('/trai-nghiem/not-available')->assertStatus(503);
        $this->assertSame($before['name'],Attraction::findOrFail($legacy->id)->name);
        ExperienceFixture::migrate();
        $this->assertNull(Attraction::findOrFail($legacy->id)->experience_published_at);
        $this->assertTrue(Attraction::findOrFail($legacy->id)->is_active);
    }
}
