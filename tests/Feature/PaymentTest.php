<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private Hotel $hotel;
    private RoomType $roomType;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        // Tạo customer
        $this->customer = Customer::factory()->create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'phone' => '0123456789',
        ]);

        // Tạo hotel
        $this->hotel = Hotel::create([
            'name' => 'Test Hotel',
            'city_id' => 1,
            'address' => 'Test Address',
            'phone' => '0987654321',
            'email' => 'hotel@example.com',
            'latitude' => 10.0,
            'longitude' => 106.0,
            'rating' => 4.5,
            'total_reviews' => 100,
            'image_url' => 'test.jpg',
            'status' => 'available',
        ]);

        // Tạo room type
        $this->roomType = RoomType::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Double Room',
            'base_price' => 1000000,
            'capacity' => 2,
            'description' => 'Test room',
        ]);

        // Tạo booking ở trạng thái pending
        $this->booking = Booking::create([
            'customer_id' => $this->customer->id,
            'hotel_id' => $this->hotel->id,
            'check_in' => now()->addDays(1)->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'num_guests' => 2,
            'total_price' => 2000000,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    /**
     * Test QR/Banking Payment
     */
    public function test_banking_payment_creates_notification_and_sends_email(): void
    {
        // Fake mail to prevent actual sending
        Mail::fake();

        // Call createManual endpoint as authenticated user
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/payments/manual', [
                'booking_id' => $this->booking->id,
                'payment_method' => 'banking',
            ]);

        // Assertions
        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Đã ghi nhận thanh toán banking']);

        // Verify booking status changed to completed
        $this->booking->refresh();
        $this->assertEquals('completed', $this->booking->status);

        // Verify payment was created with status 'paid'
        $payment = Payment::where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('paid', $payment->payment_status);
        $this->assertEquals('banking', $payment->payment_method);

        // Verify notification was created
        $notification = \App\Models\CustomerNotification::where('customer_id', $this->customer->id)
            ->where('type', 'payment_success')
            ->first();
        $this->assertNotNull($notification);
        $this->assertContains('Thanh toán QR', $notification->data['title']);

        // Verify email was sent
        Mail::assertSent(\App\Mail\BookingConfirmed::class);
    }

    /**
     * Test Cash Payment
     */
    public function test_cash_payment_creates_notification_and_sends_email(): void
    {
        Mail::fake();

        // Change booking status to pending for cash test
        $this->booking->update(['status' => 'pending']);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/payments/manual', [
                'booking_id' => $this->booking->id,
                'payment_method' => 'cash',
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Đã ghi nhận thanh toán cash']);

        // Verify payment was created with status 'pending' for cash
        $payment = Payment::where('booking_id', $this->booking->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('pending', $payment->payment_status);
        $this->assertEquals('cash', $payment->payment_method);

        // Verify notification was created for cash
        $notification = \App\Models\CustomerNotification::where('customer_id', $this->customer->id)
            ->where('type', 'payment_cash_confirmed')
            ->first();
        $this->assertNotNull($notification);
        $this->assertContains('tiền mặt', $notification->data['title']);

        // Verify email was sent
        Mail::assertSent(\App\Mail\BookingConfirmed::class);
    }

    /**
     * Test Notification Display
     */
    public function test_can_fetch_notifications(): void
    {
        // Create a notification
        \App\Models\CustomerNotification::paymentSuccess(
            $this->customer->id,
            $this->booking->id,
            $this->hotel->name,
            $this->booking->total_price
        );

        // Fetch notifications
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'customer_id', 'type', 'data', 'read_at', 'created_at']
            ],
            'meta',
            'links'
        ]);

        // Verify notification is in response
        $this->assertCount(1, $response->json('data'));
    }

    /**
     * Test Unread Count
     */
    public function test_can_get_unread_count(): void
    {
        \App\Models\CustomerNotification::paymentSuccess(
            $this->customer->id,
            $this->booking->id,
            $this->hotel->name,
            $this->booking->total_price
        );

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/notifications/unread-count');

        $response->assertStatus(200);
        $response->assertJsonFragment(['count' => 1]);
    }
}
