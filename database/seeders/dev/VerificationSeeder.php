<?php

namespace Database\Seeders\Dev;

use App\Models\Config;
use App\Models\Map;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory;

class VerificationSeeder extends Seeder
{
    use WithoutModelEvents;

    private const int MAP_COUNT = 100;
    private const int USER_COUNT = 50;

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
        $mapCodes = Map::inRandomOrder()->limit(self::MAP_COUNT)->pluck('code');
        $userIds = User::limit(self::USER_COUNT)->pluck('discord_id');

        if ($mapCodes->isEmpty() || $userIds->isEmpty()) {
            $this->command->warn("No maps or users found. Run MapSeeder and UserSeeder first.");

            return;
        }

        // Get current version from config dynamically
        $currentVersion = Config::loadVars(['current_btd6_ver'])->get('current_btd6_ver', 440);

        $this->command->info("Creating verifications for {$mapCodes->count()} maps (current version: {$currentVersion})...");

        $verificationsToInsert = [];

        foreach ($mapCodes as $mapCode) {
            $verifierCount = $this->faker->numberBetween(1, 3);
            $selectedUsers = $userIds->random($verifierCount);

            foreach ($selectedUsers as $userId) {
                $version = $this->getRandomVersion($currentVersion);

                $verificationsToInsert[] = [
                    'map_code' => $mapCode,
                    'user_id' => $userId,
                    'version' => $version,
                ];
            }
        }

        Verification::insertOrIgnore($verificationsToInsert);

        $this->command->info("Created " . count($verificationsToInsert) . " verification entries.");
    }

    /**
     * Get a random version based on distribution:
     * - 30% null
     * - 50% current version
     * - 20% random other versions
     */
    private function getRandomVersion(int $currentVersion): ?int
    {
        $rand = $this->faker->numberBetween(1, 100);

        // 30% null
        if ($rand <= 30) {
            return null;
        }

        // 30% current version
        if ($rand <= 60) {
            return $currentVersion;
        }

        // 20% random other versions (between 400 and current version - 1)
        return $this->faker->numberBetween($currentVersion - 100, $currentVersion - 1);
    }
}
