<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\AdminUser\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AITemplateCategorySeeder::class,
            AITemplateSeeder::class,
        ]);

        foreach (range(1, 50) as $number) {
            User::query()->updateOrCreate(
                ['username' => 'user'.$number],
                [
                    'name' => 'User '.$number,
                    'username' => 'user'.$number,
                    'email' => 'user'.$number.'@example.com',
                    'locale' => 'en',
                    'email_verified_at' => now(),
                    'password' => Hash::make('123456'),
                ]
            );
        }
    }
}
