<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            BusinessMenuSeeder::class,
            ContentMenuSeeder::class,
            IncentiveSeeder::class,
            LanguageSeeder::class,
            BadgeSeeder::class,
            TagSeeder::class,
            NavigationMenuSeeder::class,
        ]);
    }
}
