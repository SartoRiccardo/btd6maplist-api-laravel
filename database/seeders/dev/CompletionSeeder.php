<?php

namespace Database\Seeders\Dev;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\CompletionProof;
use App\Models\Format;
use App\Models\LeastCostChimps;
use App\Models\Map;
use App\Models\CompPlayer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompletionSeeder extends Seeder
{
    use WithoutModelEvents;

    private const int MAPS_TO_PROCESS = 100;
    private \Faker\Generator $faker;

    public function __construct()
    {
        $this->faker = \Faker\Factory::create();
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formats = Format::all();
        $maps = Map::inRandomOrder()->limit(self::MAPS_TO_PROCESS)->get();
        $users = User::inRandomOrder()->limit(100)->get();

        if ($formats->isEmpty()) {
            $this->command->error('No formats found. Please seed formats first.');
            return;
        }

        if ($maps->isEmpty()) {
            $this->command->error('No maps found. Please seed maps first.');
            return;
        }

        if ($users->isEmpty()) {
            $this->command->error('No users found. Please seed users first.');
            return;
        }

        foreach ($maps as $map) {
            $numCompletions = $this->faker->numberBetween(10, 20);

            for ($i = 0; $i < $numCompletions; $i++) {
                $this->createCompletion(
                    $map,
                    $formats,
                    $users
                );
            }
        }

        $this->command->info('Completions seeded successfully.');
    }

    /**
     * Create a completion with its meta, proofs, and player associations.
     */
    private function createCompletion(
        Map $map,
        \Illuminate\Support\Collection $formats,
        \Illuminate\Support\Collection $users
    ): void {
        $submittedOn = Carbon::instance($this->faker->dateTimeBetween('-2 years', 'now'));
        $format = $formats->random();

        // Determine number of users: 80% 1 user, 15% 2 users, 5% 3 users
        $userCount = $this->determineUserCount();
        $selectedUsers = $users->random(min($userCount, $users->count()))->pluck('discord_id');

        // Determine if completion should be deleted (10% chance)
        $isDeleted = $this->faker->boolean(10);

        // Determine if completion should be accepted (70% chance, unless deleted)
        $isAccepted = !$isDeleted && $this->faker->boolean(70);
        $acceptedBy = $isAccepted ? $users->random()->discord_id : null;

        // Determine if completion should have LCC (30% chance)
        $hasLcc = $this->faker->boolean(30);
        $lccId = null;
        if ($hasLcc) {
            $lcc = LeastCostChimps::factory()->create();
            $lccId = $lcc->id;
        }

        // Create the completion
        $completion = Completion::factory()
            ->create([
                'map_code' => $map->code,
                'submitted_on' => $submittedOn,
                'subm_notes' => $this->faker->boolean(50) ? $this->faker->sentence() : null,
            ]);

        // Create the meta
        $meta = CompletionMeta::factory()->for($completion)->create([
            'black_border' => $this->faker->boolean(30),
            'no_geraldo' => $this->faker->boolean(15),
            'lcc_id' => $lccId,
            'created_on' => $submittedOn,
            'deleted_on' => $isDeleted ? $submittedOn->copy()->addDays($this->faker->numberBetween(1, 30)) : null,
            'accepted_by_id' => $acceptedBy,
            'format_id' => $format->id,
        ]);

        // Attach players using factory
        CompPlayer::factory()
            ->count(count($selectedUsers))
            ->sequence(fn($seq) => ['user_id' => $selectedUsers[$seq->index]])
            ->create(['run' => $meta->id]);

        // Create proofs (0-3 image proofs, 0-2 video proofs)
        $imageCount = $this->faker->numberBetween(0, 3);
        $videoCount = $this->faker->numberBetween(0, 2);

        CompletionProof::factory()
            ->count($imageCount)
            ->image()
            ->create([
                'run' => $completion->id,
            ]);

        CompletionProof::factory()
            ->count($videoCount)
            ->video()
            ->create([
                'run' => $completion->id,
            ]);

        // 25% chance to create an additional overridden meta
        if ($this->faker->boolean(25)) {
            $this->createOverriddenMeta(
                $completion,
                $formats,
                $users,
                $submittedOn
            );
        }
    }

    /**
     * Create an overridden meta entry (older created_on, different values).
     */
    private function createOverriddenMeta(
        Completion $completion,
        \Illuminate\Support\Collection $formats,
        \Illuminate\Support\Collection $users,
        Carbon $originalCreatedOn
    ): void {
        $format = $formats->random();
        $pastCreatedOn = $originalCreatedOn->copy()->subDays($this->faker->numberBetween(1, 60));

        // Determine if overridden meta should have LCC (30% chance)
        $hasLcc = $this->faker->boolean(30);
        $lccId = null;
        if ($hasLcc) {
            $lcc = LeastCostChimps::factory()->create();
            $lccId = $lcc->id;
        }

        CompletionMeta::factory()->for($completion)->create([
            'black_border' => $this->faker->boolean(30),
            'no_geraldo' => $this->faker->boolean(15),
            'lcc_id' => $lccId,
            'created_on' => $pastCreatedOn,
            'deleted_on' => null,
            'accepted_by_id' => $this->faker->boolean(70) ? $users->random()->discord_id : null,
            'format_id' => $format->id,
        ]);
    }

    /**
     * Determine number of users: 80% chance of 1, 15% chance of 2, 5% chance of 3.
     */
    private function determineUserCount(): int
    {
        $roll = $this->faker->numberBetween(1, 100);

        if ($roll <= 80) {
            return 1;
        } elseif ($roll <= 95) {
            return 2;
        }

        return 3;
    }
}
