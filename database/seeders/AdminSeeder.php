<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Customer::firstOrCreate(
            ['email' => 'hahan8784@gmail.com'],
            [
                'name'          => 'Admin Hotel Booking',
                'phone'         => '0900000000',
                'password_hash' => Hash::make('311006'),
                'role'          => 'admin',
                'avatar_url'    => null,
            ]
        );
    }
}
