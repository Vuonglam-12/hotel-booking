<?php
namespace Tests\Feature;

use Tests\TestCase;
use Tests\Support\RoomTypeV2AcceptanceFixture;
use App\Support\RoomTypeV2Integration;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class RoomTypeV2SecurityQualityTest extends TestCase
{
    protected $db;
    protected function setUp(): void
    {
        parent::setUp(); Carbon::setTestNow('2026-10-12 08:00:00'); $this->withoutVite();
        Http::preventStrayRequests(); $this->db=RoomTypeV2AcceptanceFixture::initialize(); RoomTypeV2Integration::acceptance();
    }
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    public function test_unsigned_google_credential_cannot_impersonate_existing_account(): void
    {
        $encode=fn($v)=>rtrim(strtr(base64_encode(json_encode($v)),'+/','-_'),'=');
        $credential=$encode(['alg'=>'none']).'.'.$encode(['email'=>'fixture-9004@example.test','name'=>'Forged']).'.invalid';
        $tokens=$this->db->table('personal_access_tokens')->count();
        $this->postJson('/api/auth/google',['credential'=>$credential])->assertUnauthorized()->assertJsonMissingPath('token');
        $this->assertSame($tokens,$this->db->table('personal_access_tokens')->count()); Http::assertNothingSent();
    }
    public function test_invoice_page_size_is_bounded(): void
    {
        $this->actingAs(Customer::findOrFail(9001),'sanctum');
        $this->getJson('/api/invoices?per_page=1000000')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/invoices?per_page=-1')->assertUnprocessable();
        $this->getJson('/api/invoices?per_page=20')->assertOk()->assertJsonPath('per_page',20);
    }
    public function test_profile_input_does_not_mass_assign_role_email_password_or_another_id(): void
    {
        $this->actingAs(Customer::findOrFail(9001),'sanctum');
        $other=(array)$this->db->table('customer')->where('id',9004)->first();
        $original=(array)$this->db->table('customer')->where('id',9001)->first();
        $this->putJson('/api/me',['name'=>'Updated fixture','id'=>9004,'role'=>'admin','email'=>'changed@example.test','password_hash'=>'not-a-hash'])->assertOk();
        $saved=$this->db->table('customer')->where('id',9001)->first();
        $this->assertSame('user',$saved->role); $this->assertSame($original['email'],$saved->email); $this->assertSame($original['password_hash'],$saved->password_hash);
        $this->assertSame($other,(array)$this->db->table('customer')->where('id',9004)->first());
    }
    public function test_notification_mutations_and_invoice_reads_refuse_other_customer(): void
    {
        $id=$this->db->table('customer_notifications')->insertGetId(['customer_id'=>9002,'type'=>'booking','data'=>json_encode(['message'=>'Private fixture']),'created_at'=>now()]);
        $this->actingAs(Customer::findOrFail(9001),'sanctum');
        $this->postJson('/api/notifications/'.$id.'/read')->assertNotFound();
        $this->deleteJson('/api/notifications/'.$id)->assertNotFound();
        $this->getJson('/api/invoices/999999/pdf')->assertNotFound();
        $this->assertSame(9002,$this->db->table('customer_notifications')->where('id',$id)->value('customer_id'));
    }

    public function test_customer_cannot_cancel_after_check_in_cutoff(): void
    {
        $type=$this->db->table('room_type_legacy_map')->where('hotel_id',1)->where('old_room_type_id',1)->value('new_room_type_id');
        $b=app(\App\Services\BookingServiceV2::class)->create('mysql',9001,['hotel_id'=>1,'room_type_id'=>$type,'quantity'=>1,'num_guests'=>2,'check_in'=>'2026-10-15','check_out'=>'2026-10-17','payment_method'=>'banking']);
        Carbon::setTestNow('2026-10-16 08:00:00'); $this->actingAs(Customer::findOrFail(9001),'sanctum');
        $this->postJson('/api/bookings/'.$b['id'].'/cancel')->assertUnprocessable()->assertJsonValidationErrors('check_in');
        $this->assertSame('pending',$this->db->table('booking')->where('id',$b['id'])->value('status'));
    }

    private function signedCredential(array $overrides=[]): string
    {
        $options=['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA,'config'=>dirname(PHP_BINARY).'/extras/ssl/openssl.cnf'];
        $key=openssl_pkey_new($options); $this->assertNotFalse($key);
        $csr=openssl_csr_new(['commonName'=>'Synthetic Google verifier fixture'],$key,$options);
        $certificate=openssl_csr_sign($csr,null,$key,1,$options); openssl_x509_export($certificate,$pem);
        Http::fake([\App\Services\GoogleIdentityVerifier::CERTIFICATES_URL=>Http::response(['synthetic-key'=>$pem],200,['Cache-Control'=>'max-age=3600'])]);
        $claims=array_replace(['iss'=>'https://accounts.google.com','aud'=>config('services.google.client_id'),'sub'=>'synthetic-identity',
            'email'=>'fixture-9001@example.test','email_verified'=>true,'hd'=>'example.test','iat'=>now()->timestamp,'exp'=>now()->addMinutes(5)->timestamp,'name'=>'Synthetic identity'],$overrides);
        $encode=fn($v)=>rtrim(strtr(base64_encode(json_encode($v)),'+/','-_'),'=');
        $message=$encode(['alg'=>'RS256','kid'=>'synthetic-key']).'.'.$encode($claims); openssl_sign($message,$signature,$key,OPENSSL_ALGO_SHA256);
        return $message.'.'.rtrim(strtr(base64_encode($signature),'+/','-_'),'=');
    }
    public function test_verified_google_identity_uses_existing_account_without_admin_ability(): void
    {
        $before=$this->db->table('customer')->count();
        $this->postJson('/api/auth/google',['credential'=>$this->signedCredential()])->assertOk()->assertJsonPath('user.id',9001);
        $this->assertSame($before,$this->db->table('customer')->count());
        $this->assertSame(\App\Support\CustomerSessions::GOOGLE_TOKEN,$this->db->table('personal_access_tokens')->value('name'));
        Http::assertSentCount(1);
    }
    public function test_google_wrong_audience_expiry_issuer_signature_and_unverified_email_are_refused(): void
    {
        foreach ([['aud'=>'foreign-client'],['exp'=>now()->subSecond()->timestamp],['iss'=>'https://untrusted.example.test'],['email_verified'=>false]] as $claims) {
            \Illuminate\Support\Facades\Cache::forget('aurel.google.identity.certificates.v1');
            $this->postJson('/api/auth/google',['credential'=>$this->signedCredential($claims)])->assertUnauthorized()->assertJsonMissingPath('token');
        }
        $credential=$this->signedCredential(); $parts=explode('.',$credential); $parts[2]=($parts[2][0]==='a'?'b':'a').substr($parts[2],1);
        \Illuminate\Support\Facades\Cache::forget('aurel.google.identity.certificates.v1');
        $this->postJson('/api/auth/google',['credential'=>implode('.',$parts)])->assertUnauthorized();
        $this->assertSame(0,$this->db->table('personal_access_tokens')->count());
    }
    public function test_google_non_hosted_email_requires_existing_password_and_network_failure_is_closed(): void
    {
        $this->postJson('/api/auth/google',['credential'=>$this->signedCredential(['hd'=>null])])->assertForbidden();
        \Illuminate\Support\Facades\Cache::forget('aurel.google.identity.certificates.v1'); $credential=$this->signedCredential();
        // Replace the existing fake factory so an earlier certificate response cannot shadow this failure.
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests();
        Http::fake([\App\Services\GoogleIdentityVerifier::CERTIFICATES_URL=>Http::response([],503)]);
        $this->postJson('/api/auth/google',['credential'=>$credential])->assertStatus(503)->assertJsonMissingPath('token');
        $this->assertSame(0,$this->db->table('personal_access_tokens')->count());
    }
    public function test_hotel_name_does_not_break_out_of_title_element(): void
    {
        $this->db->table('hotel')->where('id',1)->update(['name'=>'</title><script>window.__phase2d_title_xss=true</script>']);
        $this->get('/hotels/1')->assertOk()->assertDontSee('<script>window.__phase2d_title_xss=true</script>',false);
    }

    public function test_raw_otp_cannot_bypass_verification_and_ten_minute_expiry(): void
    {
        \Illuminate\Support\Facades\Schema::create('password_reset_tokens',function ($t) { $t->string('email')->primary(); $t->string('token'); $t->timestamp('created_at')->nullable(); });
        $original=$this->db->table('customer')->where('id',9001)->value('password_hash');
        $email=$this->db->table('customer')->where('id',9001)->value('email');
        $this->db->table('password_reset_tokens')->insert(['email'=>$email,'token'=>\Illuminate\Support\Facades\Hash::make('123456'),'created_at'=>now()->subMinutes(20)]);
        $this->postJson('/api/reset-password',['email'=>$email,'token'=>'123456','password'=>'synthetic-new-password','password_confirmation'=>'synthetic-new-password'])->assertUnprocessable();
        $this->assertSame($original,$this->db->table('customer')->where('id',9001)->value('password_hash'));
    }

    public function test_issued_reset_token_still_resets_only_its_account_and_is_consumed(): void
    {
        \Illuminate\Support\Facades\Schema::create('password_reset_tokens',function ($t) { $t->string('email')->primary(); $t->string('token'); $t->timestamp('created_at')->nullable(); });
        $email=$this->db->table('customer')->where('id',9001)->value('email');
        $token=str_repeat('a',64); $this->db->table('password_reset_tokens')->insert(['email'=>$email,'token'=>\Illuminate\Support\Facades\Hash::make($token),'created_at'=>now()]);
        $other=$this->db->table('customer')->where('id',9004)->value('password_hash');
        $this->postJson('/api/reset-password',['email'=>$email,'token'=>$token,'password'=>'synthetic-new-password','password_confirmation'=>'synthetic-new-password'])->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('synthetic-new-password',$this->db->table('customer')->where('id',9001)->value('password_hash')));
        $this->assertSame($other,$this->db->table('customer')->where('id',9004)->value('password_hash')); $this->assertSame(0,$this->db->table('password_reset_tokens')->count());
    }

    public function test_private_responses_are_not_cached_and_public_metadata_uses_trusted_origin(): void
    {
        config(['app.url'=>'https://approved-staging.example.test']);
        $this->actingAs(Customer::findOrFail(9001),'sanctum');
        $this->getJson('/api/me')->assertOk()->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('Referrer-Policy','no-referrer');
        $this->assertStringContainsString('no-store',$this->getJson('/api/invoices')->headers->get('Cache-Control'));
        $this->withHeader('Host','untrusted.example.test')->get('/hotels/1?tracking=synthetic')->assertOk()
            ->assertSee('https://approved-staging.example.test/hotels/1',false)->assertDontSee('rel="canonical" href="http://untrusted',false);
        $this->get('/profile')->assertOk()->assertSee('noindex,noarchive',false);
        $this->get('/')->assertOk()->assertDontSee('giảm đến 50%',false);
    }

    public function test_legacy_financial_entrypoints_are_closed_before_any_query_or_mail(): void
    {
        \Illuminate\Support\Facades\Mail::fake(); \Illuminate\Support\Facades\DB::shouldReceive('connection')->never();
        $r=\Illuminate\Http\Request::create('/api/payments/manual','POST',['booking_id'=>1,'payment_method'=>'banking']);
        $this->assertSame(503,app(\App\Http\Controllers\PaymentController::class)->createManual($r)->getStatusCode());
        $this->assertSame(503,app(\App\Http\Controllers\PaymentController::class)->createPayment($r)->getStatusCode());
        $this->assertSame(503,app(\App\Http\Controllers\PaymentController::class)->retry($r)->getStatusCode());
        $this->assertSame(503,app(\App\Http\Controllers\PaymentController::class)->vnpayReturn($r)->getStatusCode());
        $this->assertSame(503,app(\App\Http\Controllers\BookingController::class)->store($r)->getStatusCode());
        $this->assertSame(503,app(\App\Http\Controllers\WebController::class)->paymentResult($r)->getStatusCode());
        $this->assertSame(503,app(\App\Http\Controllers\AuthController::class)->sendPhoneOtp($r)->getStatusCode());
        $this->assertSame(503,app(\App\Http\Controllers\AuthController::class)->verifyPhoneOtp($r)->getStatusCode());
        $admin=new Customer(['role'=>'admin']); $r->setUserResolver(fn()=> $admin);
        $this->assertSame(503,app(\App\Http\Controllers\AdminController::class)->sendEmailToCustomer($r,9001)->getStatusCode());
        \Illuminate\Support\Facades\Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_validation_does_not_flash_sensitive_credentials_and_error_headers_are_preserved(): void
    {
        \Illuminate\Support\Facades\Route::post('/__synthetic/validation',fn(\Illuminate\Http\Request $r)=>$r->validate(['name'=>'required']))->middleware('web');
        $this->from('/login')->post('/__synthetic/validation',['credential'=>'synthetic','token'=>'synthetic','otp'=>'123456','new_password'=>'synthetic'])->assertRedirect('/login');
        foreach (['credential','token','otp','new_password'] as $field) $this->assertArrayNotHasKey($field,session('_old_input',[]));
        $this->getJson('/api/admin/bookings')->assertUnauthorized()->assertHeader('Referrer-Policy','no-referrer');
        $this->actingAs(Customer::findOrFail(9001),'sanctum');
        $this->getJson('/api/invoices?per_page=1000000')->assertUnprocessable()->assertHeader('X-Content-Type-Options','nosniff');
    }
}
