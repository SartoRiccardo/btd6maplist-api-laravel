<?php

namespace Database\Seeders\Dev;

use App\Models\AdditionalCode;
use App\Models\Map;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory;

class AdditionalCodeSeeder extends Seeder
{
    use WithoutModelEvents;

    private const float MAP_PERCENTAGE_WITH_CODES = 0.10;
    private const int MAX_CODES_PER_MAP = 2;
    private const int CODE_LENGTH = 7;

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
        $totalMaps = Map::count();
        $mapsToProcess = (int) ceil($totalMaps * self::MAP_PERCENTAGE_WITH_CODES);

        if ($totalMaps === 0) {
            $this->command->warn("No maps found. Run MapSeeder first.");

            return;
        }

        $maps = Map::inRandomOrder()->limit($mapsToProcess)->pluck('code');

        $this->command->info("Creating additional codes for {$maps->count()} maps...");

        $codesToInsert = [];

        foreach ($maps as $mapCode) {
            $codeCount = $this->faker->numberBetween(1, self::MAX_CODES_PER_MAP);

            for ($i = 0; $i < $codeCount; $i++) {
                $codesToInsert[] = [
                    'code' => $this->faker->regexify('[A-Z]{7}'),
                    'description' => $this->faker->sentence(),
                    'belongs_to' => $mapCode,
                ];
            }
        }

        AdditionalCode::insertOrIgnore($codesToInsert);

        $this->command->info("Created " . count($codesToInsert) . " additional code entries.");
    }
}
