<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_customer_admin_account(): void
    {
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder'])
            ->assertOk();

        $admin = Customer::where('email', 'hahan8784@gmail.com')->first();

        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('311006', $admin->password_hash));
    }

    public function test_admin_login_api_returns_token_for_seeded_admin(): void
    {
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder'])->assertOk();

        $response = $this->postJson('/api/admin/login', [
            'email' => 'hahan8784@gmail.com',
            'password' => '311006',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'admin' => ['id', 'name', 'email', 'role'],
            ]);

        $this->assertSame('hahan8784@gmail.com', $response->json('admin.email'));
        $this->assertSame('admin', $response->json('admin.role'));
    }
}
