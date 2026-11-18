<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\Room;
use App\Models\RoomImage;
use App\Support\AccommodationImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccommodationImagesTest extends TestCase
{
    private const ACTIVE = [1, 3, 6, 7, 10, 12, 14, 17, 19, 20];
    private const PHOTO = 'image/img_hotel/hotel_ (1).jpg';

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::selectOne('SELECT DATABASE() AS name')->name !== getenv('HOLIDAYVIET_IMAGE_DATABASE')) {
            throw new \RuntimeException('Unexpected image-test database.');
        }
        DB::statement('SET TRANSACTION READ ONLY');
        DB::beginTransaction();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    #[DataProvider('validLocalPaths')]
    public function test_local_urls_are_encoded_once_and_use_the_asset_root(string $path): void
    {
        if ($path === 'absolute') {
            $path = url('/' . self::PHOTO);
        }
        $this->assertSame(asset('image/img_hotel/hotel_%20%281%29.jpg'), AccommodationImage::localUrl($path));
    }

    public static function validLocalPaths(): array
    {
        return [
            [self::PHOTO], ['/' . self::PHOTO], ['public/' . self::PHOTO],
            ['image/img_hotel/hotel_%20%281%29.jpg'], ['image\\img_hotel\\hotel_ (1).jpg'],
            [self::PHOTO . '?v=1#photo'], ['absolute'],
        ];
    }

    #[DataProvider('invalidLocalPaths')]
    public function test_invalid_or_remote_images_do_not_resolve_to_a_photo(?string $path): void
    {
        $this->assertNull(AccommodationImage::localUrl($path));
    }

    public static function invalidLocalPaths(): array
    {
        return [
            [null], [''], ['image/missing-accommodation.jpg'], ['index.php'],
            ['https://picsum.photos/seed/1/800/600'], ['https://images.unsplash.com/photo-1'],
            ['//picsum.photos/photo.jpg'], ['javascript:alert(1)'], ['data:image/png;base64,AAAA'],
            ['../.env'], ['image/%2e%2e/%2e%2e/.env'], ['image/%5c..%5c..%5c.env'],
            ['C:\\private\\photo.jpg'],
        ];
    }

    public function test_public_disk_urls_use_the_existing_storage_link(): void
    {
        $name = 'image-check-' . bin2hex(random_bytes(6)) . '.jpg';
        $file = storage_path('app/public/' . $name);
        $this->assertTrue(copy(public_path(self::PHOTO), $file));
        try {
            foreach (['storage/' . $name, 'storage/app/public/' . $name, 'public/storage/' . $name] as $path) {
                $this->assertSame(asset('storage/' . $name), AccommodationImage::localUrl($path));
            }
        } finally {
            $root = realpath(storage_path('app/public'));
            $resolved = realpath($file);
            if ($resolved && str_starts_with($resolved, $root . DIRECTORY_SEPARATOR) && basename($resolved) === $name) {
                unlink($resolved);
            }
        }
    }

    public function test_hotel_and_room_share_valid_primary_selection_and_missing_image_fallback(): void
    {
        foreach ([new Hotel(), new Room()] as $model) {
            $imageClass = $model instanceof Hotel ? HotelImage::class : RoomImage::class;
            $model->setRelation('images', new Collection([
                new $imageClass(['image_url' => 'image/missing.jpg', 'is_primary' => true]),
                new $imageClass(['image_url' => self::PHOTO, 'is_primary' => false]),
                new $imageClass(['image_url' => 'image/img_hotel/hotel_%20%281%29.jpg', 'is_primary' => false]),
            ]));
            DB::enableQueryLog();
            $this->assertSame(AccommodationImage::localUrl(self::PHOTO), $model->cover_image_url);
            $this->assertCount(1, $model->gallery_image_urls);
            $this->assertSame([], DB::getQueryLog());
            DB::disableQueryLog();
            $model->setRelation('images', new Collection());
            $this->assertSame(AccommodationImage::placeholder(), $model->cover_image_url);
            $this->assertSame([], $model->gallery_image_urls);
        }
    }

    public function test_home_eager_loads_a_cover_for_every_active_hotel(): void
    {
        $response = $this->get('/')->assertOk();
        $sections = $response->viewData('hotelSections');
        $hotels = $sections->flatMap(fn($section) => $section['hotels']);
        $this->assertEqualsCanonicalizing(self::ACTIVE, $hotels->pluck('id')->all());
        foreach ($hotels as $hotel) {
            $this->assertTrue($hotel->relationLoaded('images'));
            $this->assertNotEmpty($hotel->images);
            $this->assertNotSame(AccommodationImage::placeholder(), $hotel->cover_image_url);
        }
        $this->assertCount(9, $this->photoSources($response->getContent()));
        $this->assertStringNotContainsString('picsum', strtolower($response->getContent()));
    }

    public function test_filtered_home_pagination_keeps_each_hotel_photo(): void
    {
        $ids = [];
        foreach ([1, 2] as $page) {
            $response = $this->get('/?search=&page=' . $page)->assertOk();
            foreach ($response->viewData('hotels') as $hotel) {
                $ids[] = $hotel->id;
                $this->assertNotEmpty($hotel->images);
                $this->assertNotSame(AccommodationImage::placeholder(), $hotel->cover_image_url);
            }
        }
        $this->assertEqualsCanonicalizing(self::ACTIVE, $ids);
    }

    public function test_search_and_city_filter_apis_return_database_covers_and_only_active_hotels(): void
    {
        foreach (['/api/hotels?per_page=100', '/api/hotels?search=Hanoi', '/api/hotels?city=H%C3%A0%20N%E1%BB%99i', '/api/hotels?star=5'] as $url) {
            $data = $this->getJson($url)->assertOk()->json('data');
            $this->assertNotEmpty($data);
            foreach ($data as $item) {
                $this->assertContains($item['id'], self::ACTIVE);
                $this->assertSame('active', $item['status']);
                $this->assertSame(Hotel::with('images')->findOrFail($item['id'])->cover_image_url, $item['cover_image_url']);
            }
        }
        $this->getJson('/api/hotels?search=Park%20Hyatt')->assertOk()->assertJsonPath('total', 0);
    }

    #[DataProvider('activeHotelIds')]
    public function test_hotel_detail_gallery_room_types_and_api_use_the_same_local_sources(int $id): void
    {
        $response = $this->get('/hotels/' . $id)->assertOk();
        $hotel = $response->viewData('hotel');
        $this->assertCount(1, $hotel->gallery_image_urls);
        $this->assertStringNotContainsString('picsum', strtolower($response->getContent()));
        foreach ($this->photoSources($response->getContent()) as $source) {
            $this->assertNotSame(AccommodationImage::placeholder(), $source);
            $this->assertSame($source, AccommodationImage::localUrl($source));
        }
        foreach ($response->viewData('similarHotels') as $similar) {
            $this->assertContains($similar->id, self::ACTIVE);
            $this->assertNotEmpty($similar->images);
        }
        $api = $this->getJson('/api/hotels/' . $id)->assertOk();
        $api->assertJsonPath('cover_image_url', $hotel->cover_image_url);
        $rooms = $this->getJson('/api/hotels/' . $id . '/rooms')->assertOk()->json();
        foreach ($rooms as $room) {
            $this->assertSame(Room::with('images')->findOrFail($room['id'])->cover_image_url, $room['cover_image_url']);
        }
    }

    public static function activeHotelIds(): array
    {
        return array_map(fn($id) => [$id], self::ACTIVE);
    }

    public function test_gallery_shows_only_valid_unique_images_and_one_placeholder_when_empty(): void
    {
        $hotel = new Hotel(['name' => 'Gallery test']);
        $hotel->setRelation('images', new Collection([
            new HotelImage(['image_url' => self::PHOTO, 'is_primary' => true]),
            new HotelImage(['image_url' => self::PHOTO, 'is_primary' => false]),
            new HotelImage(['image_url' => 'image/missing.jpg', 'is_primary' => false]),
        ]));
        $this->assertCount(1, $this->photoSources(view('partials.hoteldetail.gallery-section', compact('hotel'))->render()));
        $hotel->setRelation('images', new Collection());
        $this->assertSame([AccommodationImage::placeholder()], $this->photoSources(view('partials.hoteldetail.gallery-section', compact('hotel'))->render()));
    }

    public function test_inactive_hotels_remain_outside_discovery_and_direct_urls(): void
    {
        foreach ([2, 4, 5, 8, 9, 11, 13, 15, 16, 18, 21] as $id) {
            $this->get('/hotels/' . $id)->assertNotFound();
            $this->getJson('/api/hotels/' . $id)->assertNotFound();
        }
    }

    private function photoSources(string $html): array
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $nodes = (new \DOMXPath($document))->query('//img[@data-accommodation-image and not(ancestor::template)]');
        return array_map(fn($image) => $image->getAttribute('src'), iterator_to_array($nodes));
    }
}
