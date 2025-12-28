<?php

namespace Database\Seeders;

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
        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');
        $adminName = env('ADMIN_NAME', 'admin');

        if ($adminEmail && $adminPassword) {
            User::updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => $adminName,
                    'email_verified_at' => now(),
                    'password' => bcrypt($adminPassword),
                    'role' => 'admin',
                ]
            );
            return;
        }

        if (app()->environment(['local', 'development', 'testing'])) {
            User::updateOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'admin',
                    'email_verified_at' => now(),
                    'password' => bcrypt('admin'),
                    'role' => 'admin',
                ]
            );
        }
    }
}
