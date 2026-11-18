<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\HotelRoomTypeFixture;
use Tests\TestCase;

class HotelFeedbackWorkflowTest extends TestCase
{
    private $db;
    protected function setUp(): void
    {
        parent::setUp();$this->db=HotelRoomTypeFixture::initialize();HotelRoomTypeFixture::migrate();
        foreach(['2026_10_12_000005_replace_booking_status_unique','2026_10_26_000001_create_local_booking_demo_audit','2026_04_02_013436_create_wishlist_table','2026_04_02_013439_add_foreign_keys_to_wishlist_table'] as $name)(require base_path('database/migrations/'.$name.'.php'))->up();
        $this->db->table('customer')->insert(['id'=>3,'name'=>'Other customer','email'=>'other@example.test','role'=>'user']);
        config(['booking-demo.local_enabled'=>true,'app.url'=>'http://localhost','app.debug'=>true,'logging.channels.stderr.level'=>'critical']);$this->withoutVite();Mail::fake();Http::preventStrayRequests();
    }
    private function actor(int $id=2,bool $admin=false): void
    {
        $user=Customer::findOrFail($id);$user->withAccessToken(new PersonalAccessToken(['name'=>$admin?'admin_token':'customer_token','abilities'=>$admin?['admin']:['*']]));$this->actingAs($user,$admin?'admin':'sanctum');
    }
    private function booking(string $status='completed'): int
    {
        $this->actor();$id=$this->postJson('http://localhost/api/bookings',['hotel_id'=>1,'room_type_id'=>1,'quantity'=>1,'num_guests'=>2,'check_in'=>now()->addDays(30)->toDateString(),'check_out'=>now()->addDays(32)->toDateString(),'request_key'=>bin2hex(random_bytes(16))])->assertCreated()->json('booking_id');
        if($status!=='pending'){
            $this->actor(1,true);$this->postJson('http://localhost/api/admin/demo/bookings/'.$id.'/simulate',['action'=>'success','payment_id'=>$this->db->table('payment')->value('id')])->assertOk();
            if($status==='completed')$this->putJson('http://localhost/api/admin/bookings/'.$id.'/status',['status'=>'completed'])->assertOk();
            auth('admin')->forgetUser();$this->actor();
        }
        return $id;
    }
    private function review(int $booking,array $extra=[])
    {
        $response=$this->postJson('http://localhost/api/reviews',array_replace(['hotel_id'=>1,'booking_id'=>$booking,'rating'=>4,'comment'=>'Trải nghiệm lưu trú thực tế của khách.'],$extra));
        if($response->status()===500)fwrite(STDERR,'Isolated review error: '.$response->json('message').PHP_EOL);
        return $response;
    }

    public function test_guest_cannot_write_wishlist_or_reviews(): void
    {
        $this->postJson('http://localhost/api/wishlist/1/toggle')->assertUnauthorized();
        $this->postJson('http://localhost/api/reviews',[])->assertUnauthorized();
        $this->assertSame(0,$this->db->table('wishlist')->count());$this->assertSame(0,$this->db->table('review')->count());
    }
    public function test_wishlist_is_persistent_idempotent_and_scoped_to_owner(): void
    {
        $this->actor();$this->postJson('http://localhost/api/wishlist/1')->assertOk()->assertJsonPath('liked',true);$this->postJson('http://localhost/api/wishlist/1')->assertOk();
        $this->assertSame(1,$this->db->table('wishlist')->count());$this->getJson('http://localhost/api/wishlist')->assertOk()->assertJsonCount(1)->assertJsonPath('0.hotel_id',1);
        $this->actor(3);$this->getJson('http://localhost/api/wishlist')->assertOk()->assertJsonCount(0);$this->deleteJson('http://localhost/api/wishlist/1')->assertOk();$this->assertSame(1,$this->db->table('wishlist')->count());
        $this->actor();$this->postJson('http://localhost/api/wishlist/1/toggle')->assertOk()->assertJsonPath('liked',false);$this->postJson('http://localhost/api/wishlist/1/toggle')->assertOk()->assertJsonPath('liked',true);
        $this->postJson('http://localhost/api/wishlist/99999')->assertNotFound();$this->db->table('hotel')->where('id',2)->update(['status'=>'inactive']);$this->postJson('http://localhost/api/wishlist/2')->assertNotFound();
    }
    public function test_review_requires_own_paid_booking_and_valid_input(): void
    {
        $id=$this->booking('confirmed');$this->review($id,['booking_id'=>null])->assertUnprocessable();$this->review($id,['rating'=>6])->assertUnprocessable();$this->review($id,['status'=>'approved'])->assertUnprocessable();
        $this->actor(3);$this->review($id)->assertUnprocessable();
        $this->actor();$this->review($id,['hotel_id'=>2])->assertUnprocessable();$this->assertSame(0,$this->db->table('review')->count());
    }
    public function test_review_rejects_unpaid_failed_expired_and_cancelled_bookings(): void
    {
        $id=$this->booking('pending');
        foreach(['confirmed','completed'] as $bookingStatus){
            $this->db->table('booking')->where('id',$id)->update(['status'=>$bookingStatus]);
            foreach(['pending','failed','expired'] as $paymentStatus){
                $this->db->table('payment')->where('booking_id',$id)->update(['payment_status'=>$paymentStatus]);
                $this->review($id,['payment_status'=>'success'])->assertUnprocessable();
            }
        }
        $total=$this->db->table('booking')->where('id',$id)->value('total_price');
        $this->db->table('payment')->where('booking_id',$id)->update(['payment_status'=>'success','amount'=>'1.00']);$this->review($id)->assertUnprocessable();
        $this->db->table('payment')->where('booking_id',$id)->update(['amount'=>$total]);
        foreach(['pending','cancelled','expired'] as $status){$this->db->table('booking')->where('id',$id)->update(['status'=>$status]);$this->review($id)->assertUnprocessable();}
        $this->assertSame(0,$this->db->table('review')->count());
    }
    public function test_review_is_available_after_successful_payment_without_waiting_for_completion(): void
    {
        $id=$this->booking('confirmed');$this->review($id)->assertCreated()->assertJsonPath('review.status','pending');
        $this->assertSame('confirmed',$this->db->table('booking')->where('id',$id)->value('status'));
        $this->review($id)->assertUnprocessable();$this->assertSame(1,$this->db->table('review')->count());
    }
    public function test_pending_review_requires_admin_approval_before_publication_and_rating(): void
    {
        $id=$this->booking();$review=$this->review($id)->assertCreated()->assertJsonPath('review.status','pending')->json('review.id');
        $this->getJson('http://localhost/api/hotels/1/reviews')->assertOk()->assertJsonPath('stats.total',0)->assertJsonCount(0,'reviews.data');$this->getJson('http://localhost/api/reviews/my')->assertOk()->assertJsonPath('0.status','pending');
        $this->review($id)->assertUnprocessable();$this->assertSame(1,$this->db->table('review')->count());
        $this->actor(1,true);$this->patchJson('http://localhost/api/admin/reviews/'.$review.'/status',['status'=>'approved'])->assertOk();$this->patchJson('http://localhost/api/admin/reviews/'.$review.'/status',['status'=>'approved'])->assertOk();
        $this->getJson('http://localhost/api/hotels/1/reviews')->assertOk()->assertJsonPath('stats.total',1)->assertJsonPath('reviews.data.0.status','approved');$this->getJson('http://localhost/api/hotels/1')->assertOk()->assertJsonPath('review_count',1);
        $this->assertEquals(4,$this->db->table('hotel')->where('id',1)->value('avg_rating'));$this->assertSame(1,$this->db->table('customer_notifications')->where('type','review_approved')->count());
    }
    public function test_hiding_review_preserves_content_and_excludes_it_from_customer_and_average(): void
    {
        $id=$this->booking();$review=$this->review($id)->assertCreated()->json('review.id');$this->actor(1,true);
        $this->patchJson('http://localhost/api/admin/reviews/'.$review.'/status',['status'=>'approved'])->assertOk();$this->patchJson('http://localhost/api/admin/reviews/'.$review.'/status',['status'=>'hidden'])->assertOk();
        $this->getJson('http://localhost/api/hotels/1/reviews')->assertOk()->assertJsonPath('stats.total',0);$this->assertEquals(0,$this->db->table('hotel')->where('id',1)->value('avg_rating'));
        auth('admin')->forgetUser();$this->actor();$this->getJson('http://localhost/api/reviews/my')->assertOk()->assertJsonPath('0.status','hidden')->assertJsonPath('0.comment','Trải nghiệm lưu trú thực tế của khách.');
    }
    public function test_admin_search_status_rating_pagination_and_stats_are_database_backed(): void
    {
        foreach([[1,1,'pending','Pending search'],[2,3,'approved','Approved search'],[3,5,'hidden','Hidden search']] as [$customer,$rating,$status,$comment])$this->db->table('review')->insert(['customer_id'=>$customer,'hotel_id'=>1,'rating'=>$rating,'status'=>$status,'comment'=>$comment,'created_at'=>now()]);
        $this->actor(3,true);$this->getJson('http://localhost/api/admin/reviews')->assertForbidden();$this->patchJson('http://localhost/api/admin/reviews/1/status',['status'=>'approved'])->assertForbidden();$this->deleteJson('http://localhost/api/admin/reviews/1')->assertForbidden();
        $this->actor(1,true);$this->getJson('http://localhost/api/admin/reviews?rating=3&status=approved&search=Approved')->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.rating',3)->assertJsonPath('stats.total',3)->assertJsonPath('stats.avg_rating',3);
        $this->getJson('http://localhost/api/admin/reviews?per_page=1&page=2')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('current_page',2);$this->getJson('http://localhost/api/admin/reviews?per_page=9999')->assertUnprocessable();
    }
    public function test_only_owner_or_admin_can_delete_and_rating_and_images_remain_consistent(): void
    {
        $id=$this->booking();$review=$this->review($id)->assertCreated()->json('review.id');$this->db->table('review_image')->insert(['review_id'=>$review,'image_url'=>'/test-only/review.jpg','created_at'=>now()]);
        $this->actor(3);$this->deleteJson('http://localhost/api/reviews/'.$review)->assertNotFound();$this->assertSame(1,$this->db->table('review')->count());
        $this->actor(1,true);$this->patchJson('http://localhost/api/admin/reviews/'.$review.'/status',['status'=>'approved'])->assertOk();$this->deleteJson('http://localhost/api/admin/reviews/'.$review)->assertOk();
        $this->assertSame(0,$this->db->table('review')->count());$this->assertSame(0,$this->db->table('review_image')->count());$this->assertEquals(0,$this->db->table('hotel')->where('id',1)->value('avg_rating'));
    }
    public function test_admin_can_complete_paid_stay_without_changing_payment_or_duplicate_invoice(): void
    {
        $id=$this->booking('pending');$this->actor(1,true);$this->putJson('http://localhost/api/admin/bookings/'.$id.'/status',['status'=>'completed'])->assertUnprocessable();
        $this->postJson('http://localhost/api/admin/demo/bookings/'.$id.'/simulate',['action'=>'success','payment_id'=>$this->db->table('payment')->value('id')])->assertOk();
        $this->putJson('http://localhost/api/admin/bookings/'.$id.'/status',['status'=>'completed'])->assertOk()->assertJsonPath('duplicate',false);$this->putJson('http://localhost/api/admin/bookings/'.$id.'/status',['status'=>'completed'])->assertOk()->assertJsonPath('duplicate',true);
        $this->putJson('http://localhost/api/admin/bookings/'.$id.'/status',['status'=>'confirmed'])->assertUnprocessable();$this->assertSame(1,$this->db->table('invoice')->count());$this->assertSame('success',$this->db->table('payment')->value('payment_status'));
        Mail::assertNothingSent();Http::assertNothingSent();
    }
    public function test_text_reviews_work_when_optional_image_table_is_absent(): void
    {
        // Reproduce the verified current schema on this owned test database only.
        $this->db->getSchemaBuilder()->drop('review_image');
        $id=$this->booking();$review=$this->review($id)->assertCreated()->json('review.id');
        $this->getJson('http://localhost/api/reviews/my')->assertOk()->assertJsonPath('0.id',$review);
        $this->actor(1,true);$this->patchJson('http://localhost/api/admin/reviews/'.$review.'/status',['status'=>'approved'])->assertOk();
        $this->getJson('http://localhost/api/hotels/1/reviews')->assertOk()->assertJsonPath('reviews.data.0.id',$review);
    }

    private function presentationReviews(): void
    {
        foreach([5,5,5,5,5,5,5,4,4,4,4,2,2] as $index=>$score)$this->db->table('review')->insert([
            'hotel_id'=>1,'customer_id'=>2,'rating'=>$score,'status'=>'approved','comment'=>'Approved review '.($index+1),
            'rating_cleanliness'=>$index===0?4:null,'rating_service'=>$index===0?3:null,'rating_location'=>$index===0?5:null,'created_at'=>'2026-10-26 18:00:00',
        ]);
        foreach(['pending','hidden'] as $state)$this->db->table('review')->insert(['hotel_id'=>1,'customer_id'=>3,'rating'=>1,'status'=>$state,'comment'=>'Private '.$state.' review','created_at'=>now()]);
    }

    public function test_public_review_presentation_summary_and_first_page_use_only_approved_records(): void
    {
        $this->presentationReviews();
        $response=$this->getJson('http://localhost/api/hotels/1/reviews?per_page=6')->assertOk()->assertJsonPath('stats.total',13)
            ->assertJsonPath('stats.rating_counts.5',7)->assertJsonPath('stats.rating_counts.4',4)->assertJsonPath('stats.rating_counts.2',2)
            ->assertJsonPath('stats.cleanliness_count',1)->assertJsonPath('stats.service_count',1)->assertJsonPath('stats.location_count',1)
            ->assertJsonPath('reviews.last_page',3)->assertJsonCount(6,'reviews.data');
        $this->assertEqualsWithDelta(55/13,$response->json('stats.avg_overall'),.0001);
        $html=$this->get('http://localhost/hotels/1')->assertOk()->assertSee('Đánh giá khách hàng')->assertSee('Từ 13 đánh giá đã duyệt')
            ->assertSee('4.2')->assertDontSee('Private pending review')->assertDontSee('Private hidden review')->getContent();
        $dom=new \DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new \DOMXPath($dom);
        $this->assertCount(6,$xpath->query('//*[@id="hotel-review-list"]/article'));
        $this->assertCount(3,$xpath->query('//*[@id="hotel-review-metrics"]/div[not(@hidden)]'));
        $stage=['settings_file'=>getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS'),'database'=>$this->db->getDatabaseName(),'hotel_id'=>1,'approved_count'=>13];
        file_put_contents(dirname($stage['settings_file']).'/reviews-browser-stage.json',json_encode($stage,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    }

    public function test_public_review_presentation_filter_sort_and_pagination_keep_summary_independent(): void
    {
        $this->presentationReviews();
        $this->getJson('http://localhost/api/hotels/1/reviews?rating=4&per_page=1')->assertOk()->assertJsonPath('stats.total',13)
            ->assertJsonPath('reviews.total',4)->assertJsonPath('reviews.last_page',4)->assertJsonPath('reviews.data.0.rating',4)->assertJsonPath('reviews.data.0.comment','Approved review 11');
        $this->getJson('http://localhost/api/hotels/1/reviews?rating=4&per_page=1&page=2')->assertOk()->assertJsonPath('reviews.data.0.comment','Approved review 10');
        $this->getJson('http://localhost/api/hotels/1/reviews?sort=highest')->assertOk()->assertJsonPath('reviews.data.0.rating',5);
        $this->getJson('http://localhost/api/hotels/1/reviews?sort=lowest')->assertOk()->assertJsonPath('reviews.data.0.rating',2);
        $this->getJson('http://localhost/api/hotels/1/reviews?rating=3')->assertOk()->assertJsonPath('reviews.total',0)->assertJsonPath('stats.total',13);
        foreach(['rating=0','rating=6','sort=invalid','per_page=0'] as $query)$this->getJson('http://localhost/api/hotels/1/reviews?'.$query)->assertUnprocessable();
    }

    public function test_public_review_presentation_empty_state_does_not_invent_scores_or_metrics(): void
    {
        $this->db->table('review')->insert(['hotel_id'=>1,'customer_id'=>2,'rating'=>5,'status'=>'pending','comment'=>'Waiting for approval','created_at'=>now()]);
        $this->getJson('http://localhost/api/hotels/1/reviews')->assertOk()->assertJsonPath('stats.total',0)->assertJsonPath('stats.avg_overall',null)->assertJsonPath('stats.cleanliness_count',0)->assertJsonCount(0,'reviews.data');
        $html=$this->get('http://localhost/hotels/1')->assertOk()->assertSee('Chưa có đánh giá được duyệt')->assertDontSee('Waiting for approval')->getContent();
        $dom=new \DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new \DOMXPath($dom);
        $this->assertSame('—',$xpath->query('//*[@data-review-overall]')->item(0)->textContent);
        $this->assertCount(0,$xpath->query('//*[@id="hotel-review-metrics"]/div[not(@hidden)]'));
        $this->assertCount(0,$xpath->query('//*[@id="hotel-review-list"]/article'));
    }

    public function test_public_review_presentation_escapes_content_and_works_without_optional_photos(): void
    {
        $this->db->getSchemaBuilder()->drop('review_image');
        $this->db->table('customer')->where('id',2)->update(['name'=>'<img src=x onerror=alert(1)>','avatar_url'=>'javascript:alert(1)']);
        $this->db->table('review')->insert(['hotel_id'=>1,'customer_id'=>2,'rating'=>5,'status'=>'approved','comment'=>'<script>alert(1)</script>','created_at'=>'2026-10-26 18:00:00']);
        $html=$this->get('http://localhost/hotels/1')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertSee('27/10/2026')->getContent();
        $dom=new \DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new \DOMXPath($dom);
        $this->assertCount(0,$xpath->query('//*[@id="hotel-review-list"]//script|//*[@id="hotel-review-list"]//img[@src="javascript:alert(1)"]'));
        $response=$this->getJson('http://localhost/api/hotels/1/reviews')->assertOk()->assertJsonCount(1,'reviews.data');
        $this->assertArrayNotHasKey('email',$response->json('reviews.data.0.customer'));
        $this->assertArrayNotHasKey('password_hash',$response->json('reviews.data.0.customer'));
        Mail::assertNothingSent();Http::assertNothingSent();
    }
}
