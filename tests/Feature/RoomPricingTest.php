<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\RoomPricingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\HotelRoomTypeFixture;
use Tests\TestCase;

class RoomPricingTest extends TestCase
{
    private $db;
    private RoomPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = HotelRoomTypeFixture::initialize();
        HotelRoomTypeFixture::migrate();
        $this->pricing = app(RoomPricingService::class);
        Http::preventStrayRequests();
        Mail::fake();
    }

    private function rule(string $kind, string $start, string $end, string $price, int $hotel = 1): array
    {
        return $this->pricing->saveRate($hotel, 1, ['kind' => $kind, 'start_date' => $start, 'end_date' => $end, 'price' => $price, 'label' => 'Test rate', 'amount_mode' => 'replacement']);
    }

    public function test_exact_nightly_price_precedence_crosses_year_and_excludes_checkout(): void
    {
        $this->rule('season', '2030-12-31', '2031-01-02', '1000000.11');
        $this->rule('day', '2031-01-01', '2031-01-01', '1500000.22');
        $this->rule('day', '2031-01-03', '2031-01-03', '9999999.99');
        $q = $this->pricing->quote(1, 1, '2030-12-30', '2031-01-03', 2);
        $this->assertSame('8600001.38', $q['total_price']);
        $this->assertSame('4300000.69', $q['unit_total']);
        $this->assertSame('1075000.17', $q['average_nightly_price']);
        $this->assertSame(4, $q['nights']);
        $this->assertSame(['base', 'season', 'day', 'season'], array_column($q['nightly_rates'], 'source'));
        $this->assertSame(['800000.25', '1000000.11', '1500000.22', '1000000.11'], array_column($q['nightly_rates'], 'unit_price'));
        $this->assertSame($q['quote_hash'], $this->pricing->quote(1, 1, '2030-12-30', '2031-01-03', 2)['quote_hash']);
        $this->assertNotSame($q['quote_hash'], $this->pricing->quote(1, 1, '2030-12-30', '2031-01-03', 1)['quote_hash']);
        $this->assertSame('3200001.00', $this->pricing->quote(2, 1, '2030-12-30', '2031-01-03')['total_price']);
    }

    public function test_same_priority_overlaps_are_rejected_including_boundaries_and_updates(): void
    {
        $a = $this->rule('season', '2031-01-01', '2031-01-10', '900000');
        $b = $this->rule('season', '2031-01-11', '2031-01-20', '950000');
        $this->rule('day', '2031-01-10', '2031-01-10', '1100000');
        foreach ([['season', '2031-01-10', '2031-01-12'], ['season', '2031-01-02', '2031-01-03'], ['day', '2031-01-10', '2031-01-10']] as [$kind, $from, $until]) {
            try { $this->rule($kind, $from, $until, '1000000'); $this->fail('An overlapping rate must be refused.'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('start_date', $e->errors()); }
        }
        $this->pricing->saveRate(1, 1, ['kind' => 'season', 'start_date' => $a['start_date'], 'end_date' => $a['end_date'], 'price' => '900001'], $a['id']);
        try {
            $this->pricing->saveRate(1, 1, ['kind' => 'season', 'start_date' => '2031-01-09', 'end_date' => '2031-01-20', 'price' => '950000'], $b['id']);
            $this->fail('Updating to an overlapping range must be refused.');
        } catch (ValidationException $e) { $this->assertArrayHasKey('start_date', $e->errors()); }
        $this->assertSame('2031-01-11', $this->db->table('hotel_room_rate')->where('id', $b['id'])->value('start_date'));
        $this->rule('season', '2031-01-01', '2031-01-20', '500000', 2);
        $this->assertCount(3, $this->pricing->rates(1, 1));
    }

    public function test_nightly_snapshots_survive_rate_edits_deletion_and_base_price_changes(): void
    {
        $rate = $this->rule('day', '2031-01-01', '2031-01-01', '1100000.12');
        $quote = $this->pricing->quote(1, 1, '2031-01-01', '2031-01-03');
        $line = $this->db->transaction(function () use ($quote) {
            $id = $this->db->table('booking')->insertGetId(['customer_id' => 2, 'hotel_id' => 1, 'check_in' => '2031-01-01', 'check_out' => '2031-01-03',
                'num_guests' => 2, 'total_price' => $quote['total_price'], 'status' => 'pending']);
            $line = $this->db->table('booking_room')->insertGetId(['booking_id' => $id, 'room_type_id' => 1, 'room_id' => null, 'quantity' => 1,
                'nights' => 2, 'price_at_booking' => $quote['average_nightly_price']]);
            $this->pricing->saveSnapshot($line, $quote);
            return $line;
        });
        $before = $this->pricing->snapshot($line);
        $this->pricing->saveRate(1, 1, ['kind' => 'day', 'start_date' => '2031-01-01', 'end_date' => '2031-01-01', 'price' => '2200000'], $rate['id']);
        $this->pricing->deleteRate(1, 1, $rate['id']);
        $this->db->table('hotel_room_type')->where('hotel_id', 1)->where('room_type_id', 1)->update(['base_price' => '600000.30']);
        $this->assertSame($before, $this->pricing->snapshot($line));
        $this->assertSame('1900000.37', $this->db->table('booking')->value('total_price'));
        $this->assertSame('1200000.60', $this->pricing->quote(1, 1, '2031-01-01', '2031-01-03')['total_price']);
        $this->expectException(\LogicException::class);
        $this->db->transaction(fn () => $this->pricing->saveSnapshot($line, $quote));
    }

    public function test_invalid_rules_stays_quantities_and_overflow_are_refused(): void
    {
        foreach ([['kind' => 'day', 'start_date' => '2031-01-01', 'end_date' => '2031-01-02', 'price' => '100'],
            ['kind' => 'season', 'start_date' => '2031-02-30', 'end_date' => '2031-03-02', 'price' => '100'],
            ['kind' => 'season', 'start_date' => '2031-01-01', 'end_date' => '2031-01-02', 'price' => '0'],
            ['kind' => 'season', 'start_date' => '2031-01-01', 'end_date' => '2031-01-02', 'price' => '1.001']] as $input) {
            try { $this->pricing->saveRate(1, 1, $input + ['amount_mode'=>'replacement']); $this->fail('Invalid rule accepted.'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        foreach ([['2031-01-01', '2031-01-01', 1], ['2031-01-01', '2032-01-02', 1], ['2031-01-01', '2031-01-02', 11], ['2031-01-01', '2031-01-02', 0]] as [$from, $until, $quantity]) {
            try { $this->pricing->quote(1, 1, $from, $until, $quantity); $this->fail('Invalid stay accepted.'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $this->rule('season', '2031-01-01', '2031-01-02', '9999999999.99');
        $this->expectException(ValidationException::class);
        $this->pricing->quote(1, 1, '2031-01-01', '2031-01-03');
    }

    public function test_public_quote_and_admin_rate_crud_use_existing_auth_and_hotel_ownership(): void
    {
        $url = '/api/admin/hotels/1/room-types/1/rates';
        $input = ['kind' => 'day', 'start_date' => '2031-01-01', 'end_date' => '2031-01-01', 'price' => '1000000.25', 'amount_mode'=>'replacement'];
        $this->postJson($url, $input)->assertUnauthorized();
        $this->getJson('/api/hotels/1/room-types/1/quote?check_in=2031-01-01&check_out=2031-01-02&quantity=2')
            ->assertOk()->assertJsonPath('total_price', '1600000.50');
        $admin = Customer::findOrFail(1);
        $admin->withAccessToken(new PersonalAccessToken(['name' => 'admin_token', 'abilities' => ['admin']]));
        $this->actingAs($admin, 'admin');
        $rate = $this->postJson($url, $input)->assertCreated()->json('rate.id');
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'rates');
        $this->getJson('/api/hotels/1/room-types/1/quote?check_in=2031-01-01&check_out=2031-01-02&quantity=2')
            ->assertOk()->assertJsonPath('total_price', '2000000.50')->assertJsonPath('nightly_rates.0.source', 'day');
        $this->putJson($url . '/' . $rate, array_replace($input, ['price' => '1234567.89']))->assertOk();
        $this->deleteJson('/api/admin/hotels/2/room-types/1/rates/' . $rate)->assertNotFound();
        $this->getJson('/api/hotels/3/room-types/1/quote?check_in=2031-01-01&check_out=2031-01-02')->assertNotFound();
        $this->deleteJson($url . '/' . $rate)->assertOk();
        $this->assertSame([], $this->pricing->rates(1, 1));
        $this->assertFalse(config('roomtype-v2.prepared_routes'));
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_admin_day_and_season_crud_recalculates_public_quote_after_every_change(): void
    {
        $admin=Customer::findOrFail(1);
        $admin->withAccessToken(new PersonalAccessToken(['name'=>'admin_token','abilities'=>['admin']]));
        $this->actingAs($admin,'admin');
        $url='/api/admin/hotels/1/room-types/1/rates';
        $quote='/api/hotels/1/room-types/1/quote?check_in=2030-12-30&check_out=2031-01-02&quantity=2';
        $season=['kind'=>'season','start_date'=>'2030-12-31','end_date'=>'2031-01-02','price'=>'600000.10','label'=>'Test season','amount_mode'=>'replacement'];
        $day=['kind'=>'day','start_date'=>'2031-01-01','end_date'=>'2031-01-01','price'=>'900000.20','label'=>'Test day','amount_mode'=>'replacement'];
        $seasonId=$this->postJson($url,$season)->assertCreated()->json('rate.id');
        $dayId=$this->postJson($url,$day)->assertCreated()->json('rate.id');
        $this->getJson($url)->assertOk()->assertJsonCount(2,'rates')->assertJsonPath('context.hotel_id',1)->assertJsonPath('context.room_type_id',1);
        $this->getJson($quote)->assertOk()->assertJsonPath('total_price','4600001.10')
            ->assertJsonPath('nightly_rates.0.source','base')->assertJsonPath('nightly_rates.1.source','season')->assertJsonPath('nightly_rates.2.source','day');
        $this->putJson($url.'/'.$seasonId,array_replace($season,['price'=>'650000.15']))->assertOk();
        $this->assertSame('650000.15',$this->db->table('hotel_room_rate')->where('id',$seasonId)->value('price'));
        $this->getJson($quote)->assertOk()->assertJsonPath('total_price','4700001.20')->assertJsonPath('nightly_rates.2.unit_price','900000.20');
        $this->putJson($url.'/'.$dayId,array_replace($day,['price'=>'950000.25']))->assertOk();
        $this->getJson($quote)->assertOk()->assertJsonPath('total_price','4800001.30')->assertJsonPath('room_subtotal','4800001.30');
        $this->deleteJson($url.'/'.$dayId)->assertOk();
        $this->getJson($quote)->assertOk()->assertJsonPath('total_price','4200001.10')->assertJsonPath('nightly_rates.2.source','season');
        $this->deleteJson($url.'/'.$seasonId)->assertOk();
        $this->getJson($url)->assertOk()->assertJsonCount(0,'rates');
        $this->getJson($quote)->assertOk()->assertJsonPath('total_price','4800001.50')->assertJsonPath('nightly_rates.2.source','base');
        $this->assertSame(0,$this->db->table('hotel_room_rate')->count());
        Http::assertNothingSent();Mail::assertNothingSent();
    }

    public function test_admin_rejects_overlapping_day_and_season_creates_and_updates_without_changing_saved_rates(): void
    {
        $admin=Customer::findOrFail(1);
        $admin->withAccessToken(new PersonalAccessToken(['name'=>'admin_token','abilities'=>['admin']]));
        $this->actingAs($admin,'admin');
        $url='/api/admin/hotels/1/room-types/1/rates';
        $season=['kind'=>'season','start_date'=>'2031-01-01','end_date'=>'2031-01-10','price'=>'600000','amount_mode'=>'replacement'];
        $this->postJson($url,$season)->assertCreated();
        $adjacent=$this->postJson($url,array_replace($season,['start_date'=>'2031-01-11','end_date'=>'2031-01-20']))->assertCreated()->json('rate.id');
        $day=['kind'=>'day','start_date'=>'2031-01-05','end_date'=>'2031-01-05','price'=>'900000','amount_mode'=>'replacement'];
        $this->postJson($url,$day)->assertCreated();
        $dayOther=$this->postJson($url,array_replace($day,['start_date'=>'2031-01-06','end_date'=>'2031-01-06']))->assertCreated()->json('rate.id');
        $before=$this->db->table('hotel_room_rate')->orderBy('id')->get()->toJson();
        $this->postJson($url,array_replace($season,['start_date'=>'2031-01-10','end_date'=>'2031-01-12']))->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->postJson($url,$day)->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->putJson($url.'/'.$adjacent,array_replace($season,['start_date'=>'2031-01-09','end_date'=>'2031-01-20']))->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->putJson($url.'/'.$dayOther,$day)->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->assertSame($before,$this->db->table('hotel_room_rate')->orderBy('id')->get()->toJson());
        $this->getJson('/api/hotels/1/room-types/1/quote?check_in=2031-01-05&check_out=2031-01-06')
            ->assertOk()->assertJsonPath('total_price','900000.00')->assertJsonPath('nightly_rates.0.source','day');
        Http::assertNothingSent();Mail::assertNothingSent();
    }
    public function test_surcharge_adds_to_base_with_day_priority_multiple_rooms_and_no_checkout_charge(): void
    {
        $this->db->table('hotel_room_type')->where('hotel_id',1)->where('room_type_id',1)->update(['base_price'=>'6500000.00']);
        $season=$this->pricing->saveRate(1,1,['kind'=>'season','start_date'=>'2031-01-01','end_date'=>'2031-01-03','price'=>'200000']);
        $day=$this->pricing->saveRate(1,1,['kind'=>'day','start_date'=>'2031-01-01','end_date'=>'2031-01-01','price'=>'100000']);
        $one=$this->pricing->quote(1,1,'2031-01-01','2031-01-02');
        $this->assertSame('6600000.00',$one['total_price']);
        $q=$this->pricing->quote(1,1,'2030-12-31','2031-01-03',2);
        $this->assertSame(['6500000.00','6600000.00','6700000.00'],array_column($q['nightly_rates'],'unit_price'));
        $this->assertSame(['0.00','100000.00','200000.00'],array_column($q['nightly_rates'],'surcharge'));
        $this->assertSame(['base','day','season'],array_column($q['nightly_rates'],'source'));
        $this->assertSame('39600000.00',$q['total_price']);
        $this->assertSame('6500000.00',$this->db->table('hotel_room_type')->where('hotel_id',1)->value('base_price'));
        $this->assertSame('surcharge',$day['amount_mode']);
        $zero=$this->pricing->saveRate(1,1,['kind'=>'day','start_date'=>'2031-01-02','end_date'=>'2031-01-02','price'=>'0']);
        $this->assertSame('6500000.00',$this->pricing->quote(1,1,'2031-01-02','2031-01-03')['total_price']);
        $this->pricing->deleteRate(1,1,$zero['id']);
        $this->assertSame('6700000.00',$this->pricing->quote(1,1,'2031-01-02','2031-01-03')['total_price']);
        $this->pricing->deleteRate(1,1,$season['id']);
        $this->assertSame('6500000.00',$this->pricing->quote(1,1,'2031-01-02','2031-01-03')['total_price']);
    }

    public function test_legacy_rules_keep_replacement_meaning_until_explicitly_converted(): void
    {
        $this->db->table('hotel_room_rate')->insert(['hotel_id'=>1,'room_type_id'=>1,'kind'=>'day','start_date'=>'2031-01-01','end_date'=>'2031-01-01','price'=>'100000.00']);
        $old=$this->pricing->quote(1,1,'2031-01-01','2031-01-02');
        $this->assertSame('100000.00',$old['total_price']);
        $this->assertNull($old['nightly_rates'][0]['surcharge']);
        $id=$this->db->table('hotel_room_rate')->value('id');
        $input=['kind'=>'day','start_date'=>'2031-01-01','end_date'=>'2031-01-01','price'=>'100000'];
        $this->assertSame('replacement',$this->pricing->saveRate(1,1,$input,$id)['amount_mode']);
        $this->pricing->saveRate(1,1,$input+['amount_mode'=>'surcharge'],$id);
        $this->assertSame('900000.25',$this->pricing->quote(1,1,'2031-01-01','2031-01-02')['total_price']);
    }
}
