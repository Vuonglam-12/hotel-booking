<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Tạo admin test account
        Staff::firstOrCreate(
            ['email' => 'admin@holidayviet.vn'],
            [
                'name'            => 'Admin HolidayViet',
                'phone'           => '0901000000',
                'password_hash'   => Hash::make('admin@2024'),
                'role'            => 'superadmin',
            ]
        );

        // Tạo staff test account
        Staff::firstOrCreate(
            ['email' => 'staff@holidayviet.vn'],
            [
                'name'            => 'Staff HolidayViet',
                'phone'           => '0901000001',
                'password_hash'   => Hash::make('staff@2024'),
                'role'            => 'staff',
            ]
        );
    }
}
