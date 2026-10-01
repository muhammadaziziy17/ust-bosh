<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Super Admin: .env'dagi ADMIN_EMAIL/ADMIN_PASSWORD bilan yaratiladi.
        // Production'da admin login paytida avtomatik yaratiladi ham.
        $adminEmail = strtolower(trim((string) env('ADMIN_EMAIL', '')));
        $adminPassword = (string) env('ADMIN_PASSWORD', '');

        if ($adminEmail !== '' && $adminPassword !== '' && ! User::where('role', User::ROLE_SUPER_ADMIN)->exists()) {
            User::factory()->create([
                'name' => 'Super Admin',
                'email' => $adminEmail,
                'password' => $adminPassword,
                'role' => User::ROLE_SUPER_ADMIN,
                'must_change_password' => false,
            ]);
        }

        // Demo ma'lumotlar faqat local/testing uchun
        if (app()->environment('local', 'testing')) {
            $shop = Shop::create([
                'name' => 'Demo Do\'kon',
                'status' => Shop::STATUS_ACTIVE,
            ]);

            User::factory()->create([
                'name' => 'Demo Owner',
                'email' => 'owner@demo.uz',
                'password' => 'DemoParol123',
                'role' => User::ROLE_OWNER,
                'shop_id' => $shop->id,
            ]);
        }
    }
}
