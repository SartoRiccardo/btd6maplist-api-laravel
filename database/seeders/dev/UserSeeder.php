<?php

namespace Database\Seeders\Dev;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    private const int USER_COUNT = 50;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->count(self::USER_COUNT)->create();
    }
}
