<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelImage;
use App\Support\AccommodationImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HotelDetailPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::selectOne('SELECT DATABASE() AS name')->name !== getenv('HOLIDAYVIET_IMAGE_DATABASE')) {
            throw new \RuntimeException('Presentation checks must use the configured database in READ ONLY mode.');
        }
        DB::statement('SET TRANSACTION READ ONLY');
        DB::beginTransaction();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) DB::rollBack();
        parent::tearDown();
    }

    #[DataProvider('galleryCounts')]
    public function test_gallery_adapts_to_real_local_assets_without_duplicating_tiles(int $count): void
    {
        // View-only fixtures; models are never saved and the source database remains read-only.
        $photos = Hotel::with('images')->where('status', 'active')->get()
            ->flatMap(fn ($hotel) => $hotel->gallery_image_urls)->unique()->take($count);
        $this->assertCount($count, $photos);
        $hotel = new Hotel(['name' => 'Gallery layout fixture']);
        $hotel->setRelation('images', new Collection($photos->map(fn ($url) => new HotelImage(['image_url' => $url]))->all()));
        $html = view('partials.hoteldetail.gallery-section', compact('hotel'))->render();
        $xpath = $this->xpath($html);
        $this->assertSame($count, $xpath->query('//img[@data-accommodation-image]')->length);
        $this->assertSame($count, $xpath->query('//button[@data-hotel-photo and img]')->length);
        $this->assertStringNotContainsString('picsum', $html);
    }

    public static function galleryCounts(): array
    {
        return [[1], [2], [3], [4], [5]];
    }

    public function test_detail_uses_server_room_type_prices_and_does_not_claim_undated_availability(): void
    {
        $response = $this->get('/hotels/1')->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(5, $xpath->query('//article[@data-room-type-id]')->length);
        $this->assertSame(1, $xpath->query('//button[@id="submit-booking-btn" and @disabled]')->length);
        $this->assertSame(0, $xpath->query('//article[@data-room-type-id]//*[contains(text(), "m²")]')->length);
        foreach ($xpath->query('//article[@data-room-type-id]') as $card) {
            $this->assertStringContainsString('Chọn ngày để kiểm tra phòng trống', $card->textContent);
            $this->assertStringNotContainsString('phòng trống', str_replace('Chọn ngày để kiểm tra phòng trống', '', $card->textContent));
        }
        $this->assertStringContainsString('500.000', $response->getContent());
        $this->assertStringNotContainsString('78%', $response->getContent());
        $this->assertStringNotContainsString('Miễn phí 24h', $response->getContent());
    }

    public function test_missing_room_photos_and_metadata_use_placeholder_without_inventing_facts(): void
    {
        $hotel = Hotel::with(['location', 'images', 'amenities', 'reviews.customer', 'rooms.roomType', 'rooms.images'])->findOrFail(1);
        foreach ($hotel->rooms as $room) {
            $room->setRelation('images', new Collection());
            $type = clone $room->roomType;
            $type->bed_type = null;
            $type->description = null;
            $room->setRelation('roomType', $type);
        }
        $html = view('hotel-detail', ['hotel' => $hotel, 'reviews' => collect(), 'similarHotels' => collect()])->render();
        $xpath = $this->xpath($html);
        foreach ($xpath->query('//article[@data-room-type-id]//img') as $image) {
            $this->assertSame(AccommodationImage::placeholder(), $image->getAttribute('src'));
        }
        foreach ($xpath->query('//article[@data-room-type-id]') as $card) {
            $this->assertStringNotContainsString('giường', mb_strtolower($card->textContent));
            $this->assertStringNotContainsString('m²', $card->textContent);
        }
    }

    public function test_all_hotel_amenities_are_listed_without_presenting_them_as_room_amenities(): void
    {
        $html = $this->get('/hotels/1')->assertOk()->getContent();
        $xpath = $this->xpath($html);
        $modal = $xpath->query('//*[@id="amenity-modal"]')->item(0);
        foreach (['Quầy bar', 'Đưa đón sân bay', 'Room service'] as $name) $this->assertStringContainsString($name, $modal->textContent);
        $room = $xpath->query('//*[@id="room-detail-modal"]')->item(0);
        $this->assertStringContainsString('Chưa có thông tin tiện nghi riêng', $room->textContent);
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        return new \DOMXPath($document);
    }
}
