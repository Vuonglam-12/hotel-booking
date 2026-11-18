<?php

namespace Tests\Feature;

use App\Models\Attraction;
use App\Models\AttractionImage;
use App\Models\GuideCategory;
use App\Models\GuidePost;
use App\Services\LegacyBlogImporter;
use App\Support\LegacyBlogCatalog;
use App\Support\LocationIdentity;
use Database\Seeders\AttractionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\CatalogMysqlEnvironment as Environment;
use Tests\Support\CatalogMysqlFixture as Fixture;
use Tests\Support\ExperienceFixture;

final class CatalogDataSafetyMysqlTest extends TestCase
{
    public function createApplication() { return Environment::application(); }

    protected function setUp(): void
    {
        parent::setUp();
        Environment::assertTarget();
        DB::beginTransaction();
        Fixture::locations();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        try { Mail::assertNothingSent(); Notification::assertNothingSent(); Http::assertNothingSent(); }
        finally { while (DB::transactionLevel() > 0) DB::rollBack(); parent::tearDown(); }
    }

    private function importer(): LegacyBlogImporter { return app(LegacyBlogImporter::class); }
    private function snapshot(): string { return DB::table('guide_post')->orderBy('id')->get()->toJson(); }

    public function test_first_import_preserves_all_nine_sources_as_non_featured_drafts(): void
    {
        $files = [resource_path('views/blog.blade.php'), resource_path('views/blog_detail.blade.php'),
            ...glob(resource_path('views/content/blog-*-content.blade.php')),
            ...array_map(fn ($meta) => public_path('image/'.$meta['image']), LegacyBlogCatalog::INDEX)];
        $before = array_map(fn ($path) => hash_file('sha256', $path), $files);
        $report = $this->importer()->apply();
        $this->assertCount(9, $report);
        $this->assertSame(['draft'], array_values(array_unique(array_column($report, 'status'))));
        $this->assertSame(9, GuidePost::where('status', 'draft')->count());
        foreach (LegacyBlogCatalog::INDEX as $meta) {
            $post = GuidePost::where('legacy_id', $meta['id'])->firstOrFail();
            $file = resource_path('views/content/blog-'.$meta['id'].'-content.blade.php');
            $raw = is_file($file) ? file_get_contents($file) : null;
            // Native MySQL JSON canonicalizes object key order; compare every value, not key order.
            $this->assertEquals($meta, $post->legacy_metadata['index']);
            $this->assertEquals(collect(LegacyBlogCatalog::DETAIL)->firstWhere('id', $meta['id']), $post->legacy_metadata['detail']);
            $this->assertSame($raw, $post->legacy_body);
            $this->assertSame($raw === null ? null : hash('sha256', $raw), $post->legacy_content_sha256);
            $this->assertSame('image/'.$meta['image'], $post->cover_path);
            $this->assertNull($post->published_at);
            $this->assertFalse($post->is_featured);
            $this->assertNull($post->author_name);
            $this->assertNull($post->location_id);
            $this->get('/blog/'.$meta['id'])->assertRedirect('/cam-nang?legacy='.$meta['id']);
            $this->getJson('/api/guides/'.$post->slug)->assertNotFound();
        }
        $one = GuidePost::where('legacy_id', 1)->firstOrFail();
        $this->assertSame('complete', $one->legacy_import_state);
        $this->assertStringContainsString('Vịnh Vĩnh Hy', $one->body_html);
        $this->assertStringNotContainsString('<script', $one->body_html);
        $this->assertSame(7, GuidePost::where('legacy_import_state', 'empty')->count());
        $this->assertSame('missing', GuidePost::where('legacy_id', 3)->value('legacy_import_state'));
        $this->getJson('/api/guides')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('featured', null);
        $this->assertSame($before, array_map(fn ($path) => hash_file('sha256', $path), $files));
    }

    public function test_reimport_does_not_change_rows_timestamps_categories_or_publication(): void
    {
        $this->importer()->apply();
        $before = $this->snapshot();
        $categories = GuideCategory::orderBy('id')->get()->toJson();
        $report = $this->importer()->apply();
        $this->assertSame($before, $this->snapshot());
        $this->assertSame($categories, GuideCategory::orderBy('id')->get()->toJson());
        $this->assertSame(['kept'], array_values(array_unique(array_column($report, 'result'))));
        $this->assertSame(9, GuidePost::count());
        $this->assertSame(0, GuidePost::where('status', 'published')->count());
    }

    public static function editorialDecisions(): array
    {
        return ['edited draft' => ['draft', false, false], 'already published' => ['published', false, false],
            'scheduled by admin' => ['published', true, false], 'archived by admin' => ['draft', false, true]];
    }

    /** @dataProvider editorialDecisions */
    public function test_existing_admin_decisions_are_never_overwritten(string $status, bool $future, bool $archived): void
    {
        // This is synthetic editorial data, never a request to publish a real legacy article.
        $post = Fixture::guide(['title' => 'Synthetic editorial decision', 'status' => $status,
            'cover_path' => 'storage/retained-editorial-cover.png', 'is_featured' => true,
            'published_at' => $future ? now()->addMonth() : now()->subDay(), 'archived_at' => $archived ? now() : null]);
        $post->legacy_id = 1;
        $post->legacy_body = 'Retained editorial archive';
        $post->legacy_metadata = ['editorial_decision' => 'retain'];
        $post->save();
        $before = DB::table('guide_post')->where('id', $post->id)->first();
        for ($i = 0; $i < 2; $i++) {
            $report = $this->importer()->apply();
            $this->assertSame(['id' => 1, 'result' => 'kept', 'status' => $status], $report[0]);
            $this->assertEquals($before, DB::table('guide_post')->where('id', $post->id)->first());
        }
        $this->assertSame(9, GuidePost::count());
        $this->assertSame(8, GuidePost::where('legacy_id', '<>', 1)->where('status', 'draft')->count());
    }

    public function test_changed_unsafe_legacy_source_is_archived_privately_and_not_published_or_evaluated(): void
    {
        $original = base_path();
        $directory = Environment::settings()['runtime'].'/unsafe-source-'.bin2hex(random_bytes(5));
        $resource = $directory.'/resources';
        mkdir($resource.'/views/content', 0777, true);
        $raw = '<h2 onclick="bad()">Unreviewed content</h2><script>window.bad=1</script><?php throw new RuntimeException("must never evaluate"); ?>';
        file_put_contents($resource.'/views/content/blog-1-content.blade.php', $raw);
        $this->app->setBasePath($directory);
        try {
            $this->importer()->apply();
            $one = GuidePost::where('legacy_id', 1)->firstOrFail();
            $this->assertSame('needs_review', $one->legacy_import_state);
            $this->assertSame($raw, $one->legacy_body);
            $this->assertSame(hash('sha256', $raw), $one->legacy_content_sha256);
            $this->assertNull($one->body_html);
            $this->assertSame('draft', $one->status);
            $this->assertNull($one->published_at);
            $this->getJson('/api/guides/'.$one->slug)->assertNotFound();
        } finally { $this->app->setBasePath($original); }
    }

    public function test_missing_source_and_missing_cover_remain_drafts_without_fabricated_content(): void
    {
        $originalBase = base_path(); $originalPublic = public_path();
        $directory = Environment::settings()['runtime'].'/missing-source-'.bin2hex(random_bytes(5));
        mkdir($directory.'/resources/views/content', 0777, true);
        mkdir($directory.'/public', 0777, true);
        file_put_contents($directory.'/resources/views/content/blog-2-content.blade.php', '');
        $this->app->setBasePath($directory)->usePublicPath($directory.'/public');
        try {
            $inspection = $this->importer()->inspect();
            $this->assertSame([false], array_values(array_unique(array_column($inspection, 'cover_exists'))));
            $this->importer()->apply();
            foreach (GuidePost::all() as $post) {
                $this->assertNull($post->body_html);
                $this->assertNull($post->published_at);
                $this->assertSame('draft', $post->status);
                $this->assertFalse($post->legacy_metadata['cover_existed_at_import']);
            }
            $this->assertSame('empty', GuidePost::where('legacy_id', 2)->value('legacy_import_state'));
            $this->assertSame(8, GuidePost::where('legacy_import_state', 'missing')->count());
        } finally { $this->app->setBasePath($originalBase)->usePublicPath($originalPublic); }
    }

    public function test_invalid_slug_conflict_rolls_back_the_entire_import_without_replacing_existing_data(): void
    {
        $meta = LegacyBlogCatalog::INDEX[2];
        Fixture::guide(['slug' => Str::limit(Str::slug($meta['title']), 180, '').'-blog-'.$meta['id']]);
        $before = $this->snapshot(); $categories = GuideCategory::orderBy('id')->get()->toJson();
        try { $this->importer()->apply(); $this->fail('A conflicting slug must stop the import.'); }
        catch (QueryException $error) { $this->assertSame('23000', $error->errorInfo[0]); }
        $this->assertSame($before, $this->snapshot());
        $this->assertSame($categories, GuideCategory::orderBy('id')->get()->toJson());
        $this->assertSame(0, GuidePost::whereNotNull('legacy_id')->count());
    }

    public function test_command_inspection_does_not_open_a_database_and_apply_only_imports_drafts(): void
    {
        config(['database.default' => 'unavailable-inspection-only']);
        try {
            $this->assertSame(0, Artisan::call('guides:import-legacy'));
            $output = Artisan::output();
            $this->assertStringContainsString('Inspection only', $output);
            $this->assertStringNotContainsString('published', $output);
        } finally { config(['database.default' => 'catalog_test']); }
        $this->assertSame(0, GuidePost::count());
        $this->assertSame(0, Artisan::call('guides:import-legacy', ['--apply' => true]));
        $this->assertStringContainsString('pending Admin approval', Artisan::output());
        $this->assertSame(9, GuidePost::where('status', 'draft')->count());
        $this->assertSame(0, GuidePost::whereNotNull('published_at')->count());
    }

    public function test_primary_guard_refuses_before_any_connection_attempt(): void
    {
        // An unsupported driver makes even an accidental connection fail locally; it cannot reach any server.
        config(['database.default' => 'blocked-primary', 'database.connections.blocked-primary' => [
            'driver' => 'unsupported-must-not-connect', 'database' => 'hotel_booking']]);
        try {
            $this->assertSame(1, Artisan::call('guides:import-legacy', ['--apply' => true]));
            $this->assertStringContainsString('Refusing hotel_booking', Artisan::output());
        } finally {
            $connections = config('database.connections');
            unset($connections['blocked-primary']);
            config(['database.default' => 'catalog_test', 'database.connections' => $connections]);
        }
        $this->assertSame(0, GuidePost::count());
    }

    public function test_all_47_seed_rows_match_reviewed_keys_without_creating_locations_or_publishing(): void
    {
        $handle = fopen(base_path('docs/validation/attraction-seed-review-phase5a.csv'), 'r');
        try {
            $header = fgetcsv($handle); $review = [];
            while (($row = fgetcsv($handle)) !== false) $review[] = array_combine($header, $row);
        } finally { fclose($handle); }
        $this->assertCount(47, $review);
        $before = DB::table('location')->orderBy('id')->get()->toJson();
        $expected = ['vn-ha-noi' => 72, 'vn-ho-chi-minh' => 901, 'vn-da-lat' => 18, 'vn-phu-quoc' => 405];
        $this->assertSame(array_keys(LocationIdentity::SEED_NAMES), array_keys($expected));
        $source = file_get_contents(database_path('seeders/AttractionSeeder.php'));
        $this->assertSame(47, preg_match_all("/'location_key'\\s*=>/", $source));
        $this->assertDoesNotMatchRegularExpression("/'location_id'\\s*=>\\s*[0-9]+/", $source);
        app(AttractionSeeder::class)->run(); $quality = [];
        foreach ($review as $row) {
            $item = Attraction::where('name', $row['name'])->where('item_type', $row['item_type'])->firstOrFail();
            $this->assertSame($expected[$row['source_location_key']], $item->location_id);
            $this->assertSame(LocationIdentity::SEED_NAMES[$row['source_location_key']], $row['canonical_name']);
            $this->assertFalse($item->is_active);
            $this->assertNull($item->experience_verified_at); $this->assertNull($item->experience_published_at);
            $this->assertNull($item->lat); $this->assertNull($item->lng); $this->assertNull($item->address);
            $this->assertSame(0, $item->images()->count());
            $quality[] = ['key' => $row['source_location_key'], 'name' => $item->name, 'type' => $item->item_type,
                'lat' => $item->lat, 'lng' => $item->lng, 'address' => $item->address,
                'price_min' => $item->price_min, 'price_max' => $item->price_max,
                'price_review' => 'UNVERIFIED_SOURCE_ESTIMATE_NOT_PUBLIC', 'image_count' => $item->images()->count(),
                'description_present' => trim($item->description ?? '') !== '', 'image_rights' => 'NO_SEED_IMAGE_TO_REVIEW'];
        }
        app(AttractionSeeder::class)->run();
        $this->assertSame(48, Attraction::count()); // 47 samples + one untouched migration baseline row.
        $this->assertSame($before, DB::table('location')->orderBy('id')->get()->toJson());
        $this->getJson('/api/experiences')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/experiences/filters')->assertOk()->assertJsonCount(0, 'locations');
        file_put_contents(Environment::settings()['runtime'].'/seed-data-quality.json', json_encode($quality, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function invalidLocations(): array { return ['missing' => [false], 'ambiguous' => [true]]; }

    /** @dataProvider invalidLocations */
    public function test_missing_or_ambiguous_location_stops_seed_before_any_write(bool $ambiguous): void
    {
        if ($ambiguous) {
            DB::table('location')->where('id', 405)->update(['code' => null]);
            DB::table('location')->insert(['name' => LocationIdentity::SEED_NAMES['vn-phu-quoc'], 'country' => 'Vietnam']);
        } else DB::table('location')->where('id', 405)->delete();
        $before = DB::table('attraction')->orderBy('id')->get()->toJson();
        try { app(AttractionSeeder::class)->run(); $this->fail('Invalid identity must stop the seed.'); }
        catch (\RuntimeException $error) { $this->assertStringContainsString('Missing or ambiguous location', $error->getMessage()); }
        $this->assertSame($before, DB::table('attraction')->orderBy('id')->get()->toJson());
    }

    public function test_publication_gates_are_shared_by_listing_detail_filters_and_guide_suggestions(): void
    {
        $visible = ExperienceFixture::published(['slug' => 'eligible-experience']);
        $overrides = [['is_active' => false], ['experience_verified_at' => null], ['experience_published_at' => null],
            ['experience_published_at' => now()->addDay()], ['experience_archived_at' => now()],
            ['experience_location_code' => 'vn-da-lat'], ['experience_category' => null], ['item_type' => 'hotel'], ['slug' => null],
            ['location_id' => 405, 'experience_location_code' => 'vn-phu-quoc']];
        DB::table('location')->where('id', 405)->update(['code' => null]);
        foreach ($overrides as $index => $override) {
            $hidden = ExperienceFixture::published(['slug' => 'hidden-'.$index, ...$override]);
            if ($hidden->slug) {
                $this->getJson('/api/experiences/'.$hidden->slug)->assertNotFound();
                $this->get('/trai-nghiem/'.$hidden->slug)->assertNotFound();
            }
        }
        $this->getJson('/api/experiences')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $visible->id);
        $this->getJson('/api/experiences/filters')->assertOk()->assertJsonCount(1, 'locations')->assertJsonPath('locations.0.code', 'vn-ha-noi');
        $this->getJson('/api/experiences?location=vn-not-existing')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/experiences?location=vn-phu-quoc')->assertOk()->assertJsonCount(0, 'data');
        $guide = Fixture::guide();
        $this->getJson('/api/guides/'.$guide->slug)->assertOk()->assertJsonCount(1, 'data.experiences')->assertJsonPath('data.experiences.0.id', $visible->id);
        $this->assertFalse(Schema::hasColumn('location', 'is_active'));
        $this->assertFalse(Schema::hasColumn('location', 'status'));
    }

    public function test_missing_or_unattributed_images_use_empty_gallery_and_do_not_expose_estimated_prices(): void
    {
        $item = ExperienceFixture::published(['slug' => 'missing-gallery', 'address' => null, 'lat' => null, 'lng' => null,
            'price_min' => 12345, 'price_max' => 67890]);
        AttractionImage::create(['attraction_id' => $item->id, 'image_url' => 'image/L-1.jpg', 'source_note' => '', 'is_primary' => true]);
        AttractionImage::create(['attraction_id' => $item->id, 'image_url' => 'storage/missing-file.png', 'source_note' => 'Synthetic missing-file review']);
        $data = $this->getJson('/api/experiences/'.$item->slug)->assertOk()->assertJsonCount(0, 'data.gallery')->json('data');
        $this->assertNull($data['cover']); $this->assertNull($data['address']);
        foreach (['price_min', 'price_max', 'rating', 'lat', 'lng'] as $field) $this->assertArrayNotHasKey($field, $data);
        $this->get('/trai-nghiem/'.$item->slug)->assertOk()->assertDontSee('12345')->assertDontSee('67890');
    }
}
