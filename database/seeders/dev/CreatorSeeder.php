<?php

namespace Database\Seeders\Dev;

use App\Models\Creator;
use App\Models\Map;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory;

class CreatorSeeder extends Seeder
{
    use WithoutModelEvents;

    private const int MAP_COUNT = 100;
    private const int USER_COUNT = 100;

    private \Faker\Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $map_codes = Map::inRandomOrder()->limit(self::MAP_COUNT)->pluck('code');
        $user_ids = User::limit(self::USER_COUNT)->pluck('discord_id');

        if ($map_codes->isEmpty() || $user_ids->isEmpty()) {
            $this->command->warn("No maps or users found. Run MapSeeder and UserSeeder first.");

            return;
        }

        $this->command->info("Assigning creators to {$map_codes->count()} maps...");

        $creatorsToInsert = [];

        foreach ($map_codes as $map_code) {
            $creatorCount = $this->faker->numberBetween(1, 3);
            $selectedUsers = $user_ids->random($creatorCount);

            foreach ($selectedUsers as $user) {
                $creatorsToInsert[] = [
                    'user_id' => $user,
                    'map_code' => $map_code,
                    'role' => $this->faker->boolean(70) ? $this->faker->word() : null,
                ];
            }
        }

        Creator::insertOrIgnore($creatorsToInsert);

        $this->command->info("Created " . count($creatorsToInsert) . " creator entries.");
    }
}
