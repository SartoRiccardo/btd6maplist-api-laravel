<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DevSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \DB::transaction(function () {
            $this->call([
                \Database\Seeders\DatabaseSeeder::class,
                \Database\Seeders\Dev\UserSeeder::class,
                \Database\Seeders\Dev\MapSeeder::class,
                \Database\Seeders\Dev\CreatorSeeder::class,
                \Database\Seeders\Dev\VerificationSeeder::class,
                \Database\Seeders\Dev\AdditionalCodeSeeder::class,
            ]);
        });
    }
}
