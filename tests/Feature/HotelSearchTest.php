<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Support\HotelRoomTypeFixture;
use Tests\TestCase;

class HotelSearchTest extends TestCase
{
    private $db;
    private string $arrival;
    private string $departure;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db=HotelRoomTypeFixture::initialize();HotelRoomTypeFixture::migrate();
        (require base_path('database/migrations/2026_10_26_000001_create_local_booking_demo_audit.php'))->up();
        Schema::table('location',fn($table)=>$table->boolean('is_active')->default(true));
        $this->db->table('location')->where('id',1)->update(['name'=>'Hà Nội']);
        $this->db->table('location')->insert([['id'=>2,'name'=>'Đà Lạt','is_active'=>true],['id'=>3,'name'=>'Địa phương ẩn','is_active'=>false]]);
        $this->db->table('hotel')->where('id',1)->update(['name'=>'Hanoi River Hotel']);
        $this->db->table('hotel')->where('id',2)->update(['name'=>'Khách sạn Hồ Gươm']);
        $this->db->table('hotel')->where('id',3)->update(['name'=>'Khách sạn ẩn','status'=>'inactive']);
        $this->db->table('hotel')->insert([
            ['id'=>4,'name'=>'Dalat Garden Hotel','location_id'=>2,'latitude'=>0,'longitude'=>0,'status'=>'active'],
            ['id'=>5,'name'=>'Hotel in hidden location','location_id'=>3,'latitude'=>0,'longitude'=>0,'status'=>'active'],
        ]);
        $this->db->table('room')->insert(['hotel_id'=>4,'room_type_id'=>1,'room_number'=>'401','price'=>800000,'capacity'=>4,'status'=>'available']);
        $this->db->table('hotel_room_type')->where('hotel_id',1)->update(['capacity'=>2,'base_price'=>1000000]);
        $this->db->table('hotel_room_type')->where('hotel_id',2)->update(['capacity'=>4,'base_price'=>500000]);
        $this->arrival=now()->addDays(30)->toDateString();$this->departure=now()->addDays(33)->toDateString();
        $this->withoutVite();Http::preventStrayRequests();Mail::fake();
    }

    private function url(array $filters=[]): string
    {
        return '/api/hotels?'.http_build_query(array_replace(['check_in'=>$this->arrival,'check_out'=>$this->departure,'guests'=>3,'location_id'=>1],$filters));
    }

    private function reserve(int $quantity,string $arrival,string $departure,string $status='confirmed'): int
    {
        $id=$this->db->table('booking')->insertGetId(['customer_id'=>2,'hotel_id'=>1,'check_in'=>$arrival,'check_out'=>$departure,'num_guests'=>2,'total_price'=>1000000,'status'=>$status]);
        $this->db->table('booking_room')->insert(['booking_id'=>$id,'room_type_id'=>1,'room_id'=>null,'quantity'=>$quantity,'nights'=>1,'price_at_booking'=>1000000]);
        return $id;
    }

    public function test_suggestions_use_real_ids_active_hotels_and_locations_and_accept_accentless_search(): void
    {
        $this->getJson('/api/hotels/suggestions')->assertOk()->assertJsonCount(2,'locations')->assertJsonCount(0,'hotels');
        $this->getJson('/api/hotels/suggestions?q=ha%20noi')->assertOk()->assertJsonPath('locations.0.id',1)->assertJsonPath('locations.0.hotels_count',2)->assertJsonCount(2,'hotels');
        $this->getJson('/api/hotels/suggestions?q=da%20lat')->assertOk()->assertJsonPath('locations.0.id',2)->assertJsonPath('hotels.0.id',4);
        $this->getJson('/api/hotels/suggestions?q=khongtontai')->assertOk()->assertJsonCount(0,'locations')->assertJsonCount(0,'hotels');
        $this->getJson('/api/hotels?search=ho%20guom')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.id',2);
        $this->getJson('/api/hotels?search=ha%20noi')->assertOk()->assertJsonPath('total',2);
        $this->getJson('/api/hotels?hotel_id=4')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.id',4);
        $this->getJson('/api/hotels?location_id=3')->assertOk()->assertJsonPath('total',0);
    }

    public function test_dates_and_group_capacity_filter_before_pagination_and_use_hotel_owned_prices(): void
    {
        $this->reserve(4,$this->arrival,$this->departure);
        $this->getJson($this->url(['per_page'=>1]))->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.id',2)
            ->assertJsonPath('data.0.search_availability.required_rooms',1)->assertJsonPath('data.0.search_availability.available_rooms',5)
            ->assertJsonPath('data.0.search_availability.dates_checked',true);
        $result=$this->getJson($this->url())->json('data.0');
        $this->assertEquals(500000,$result['rooms_min_price']);
        $this->assertArrayNotHasKey('rooms',$result);
        $this->getJson($this->url(['max_price'=>400000]))->assertOk()->assertJsonPath('total',0);
        $this->db->table('booking')->update(['check_out'=>$this->arrival,'check_in'=>now()->addDays(29)->toDateString()]);
        $this->getJson($this->url(['per_page'=>1]))->assertOk()->assertJsonPath('total',2)->assertJsonPath('last_page',2)->assertJsonCount(1,'data');
    }

    public function test_availability_uses_peak_occupancy_not_sum_of_non_overlapping_bookings(): void
    {
        $middle=now()->addDays(31)->toDateString();
        $this->reserve(3,$this->arrival,$middle);
        $this->reserve(3,$middle,$this->departure);
        $response=$this->getJson($this->url(['hotel_id'=>1,'guests'=>4]))->assertOk()->assertJsonPath('total',1)
            ->assertJsonPath('data.0.search_availability.available_rooms',2)->assertJsonPath('data.0.search_availability.required_rooms',2);
        $this->get('/?'.http_build_query(['hotel_id'=>1,'check_in'=>$this->arrival,'check_out'=>$this->departure,'guests'=>4]))->assertOk()
            ->assertSee('2 phòng cho 4 khách')->assertSee('room_type_id=1',false)->assertSee('quantity=2',false);
    }

    public function test_expired_holds_do_not_hide_inventory_and_search_never_mutates_booking_data(): void
    {
        $id=$this->reserve(5,$this->arrival,$this->departure,'pending');
        $this->db->table('booking_demo')->insert(['booking_id'=>$id,'customer_id'=>2,'request_key'=>'search-only-expired','request_hash'=>str_repeat('a',64),'room_name'=>'Deluxe','expires_at_epoch'=>time()-60,'created_at_epoch'=>time()-960]);
        $writes=[];$this->db->listen(function($event)use(&$writes){if(preg_match('/^\s*(INSERT|UPDATE|DELETE|ALTER|CREATE|DROP)\b/i',$event->sql))$writes[]=$event->sql;});
        $this->getJson($this->url(['hotel_id'=>1]))->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.search_availability.available_rooms',5);
        $this->assertSame('pending',$this->db->table('booking')->where('id',$id)->value('status'));
        $this->assertSame([],$writes);
        Mail::assertNothingSent();Http::assertNothingSent();
    }

    public function test_invalid_stays_guest_groups_and_sort_input_are_rejected_by_backend(): void
    {
        foreach([
            ['check_in'=>'','check_out'=>$this->departure],['check_out'=>$this->arrival],['check_in'=>now()->subDay()->toDateString()],
            ['check_out'=>now()->addDays(400)->toDateString()],['guests'=>0],['guests'=>21],['guests'=>2.5],
            ['guests'=>4,'adults'=>2,'children'=>1,'infants'=>0],['guests'=>2,'adults'=>0,'children'=>2],['dir'=>'desc;DROP TABLE hotel'],
        ] as $invalid)$this->getJson($this->url($invalid))->assertUnprocessable();
        $this->getJson($this->url(['guests'=>4,'adults'=>2,'children'=>1,'infants'=>1]))->assertOk();
        $this->assertSame(5,$this->db->table('hotel')->count());
    }

    public function test_inactive_room_types_and_maintenance_rooms_are_not_search_results(): void
    {
        $this->db->table('hotel_room_type')->where('hotel_id',1)->update(['status'=>'inactive']);
        $this->db->table('room')->where('hotel_id',2)->update(['status'=>'maintenance']);
        $this->getJson($this->url())->assertOk()->assertJsonPath('total',0);
        $this->getJson('/api/hotels?hotel_id=3&guests=2')->assertOk()->assertJsonPath('total',0);
    }

    public function test_search_uses_the_vietnam_calendar_day_even_before_utc_midnight(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-26 18:00:00','UTC'));
        try {
            $this->getJson('/api/hotels?check_in=2026-10-26&check_out=2026-10-28')->assertUnprocessable();
            $this->getJson('/api/hotels?check_in=2026-10-27&check_out=2026-10-28&guests=2')->assertOk();
            $this->get('/')->assertOk()->assertSee('min="2026-10-27"',false);
        } finally {$this->travelBack();}
    }
}
