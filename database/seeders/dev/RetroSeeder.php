<?php

namespace Database\Seeders\Dev;

use App\Models\RetroGame;
use App\Models\RetroMap;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory;

class RetroSeeder extends Seeder
{
    use WithoutModelEvents;

    private const int GAME_COUNT = 5;

    private const array MAP_COUNTS = [10, 20, 50, 100, 150];

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
        $this->command->info("Creating " . self::GAME_COUNT . " retro games...");

        $gamesToInsert = [];
        for ($i = 0; $i < self::GAME_COUNT; $i++) {
            $gamesToInsert[] = [
                'game_id' => $this->faker->unique()->randomNumber(5),
                'category_id' => $this->faker->randomNumber(3),
                'subcategory_id' => $this->faker->randomNumber(3),
                'game_name' => $this->faker->words(2, true),
                'category_name' => $this->faker->word(),
                'subcategory_name' => $this->faker->word(),
            ];
        }

        RetroGame::insertOrIgnore($gamesToInsert);

        // Get the inserted games with their IDs
        $games = RetroGame::orderByDesc('id')->limit(self::GAME_COUNT)->get()->reverse();

        $this->command->info("Creating retro maps for " . $games->count() . " games...");

        $mapsToInsert = [];
        foreach ($games as $index => $game) {
            $mapCount = self::MAP_COUNTS[$index] ?? 50;
            $this->command->info("Game {$game->game_name}: creating {$mapCount} maps...");

            for ($i = 0; $i < $mapCount; $i++) {
                $mapsToInsert[] = [
                    'name' => $this->faker->words(3, true),
                    'sort_order' => $i + 1,
                    'preview_url' => $this->faker->url(),
                    'retro_game_id' => $game->id,
                ];
            }

        }
        RetroMap::insertOrIgnore($mapsToInsert);
    }
}
