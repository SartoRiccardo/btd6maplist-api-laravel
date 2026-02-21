<?php

namespace Tests\Abstract;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\RetroMap;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * Abstract Class TestsLeaderboardValueBehavior
 *
 * Extends TestsLeaderboardCommonBehavior to add value-specific tests
 * (black_border, no_geraldo, least_cores, etc.)
 */
abstract class TestsLeaderboardValueBehavior extends TestsLeaderboardCommonBehavior
{
    /**
     * Get a factory for valid completion metas (e.g., with black_border=true)
     * Should return CompletionMeta::factory() with appropriate state applied
     */
    abstract protected function validCompletionMetaFactory(): mixed;

    /**
     * Get a factory for invalid completion metas (e.g., with black_border=false)
     * Should return CompletionMeta::factory() with appropriate state applied
     */
    abstract protected function invalidCompletionMetaFactory(): mixed;

    /**
     * Get the query parameter value for the leaderboard endpoint
     * (e.g., 'black_border', 'no_geraldo', 'least_cores')
     */
    abstract protected function leaderboardValueParam(): string;

    /**
     * Override to always include value parameter
     */
    protected function getLeaderboardData(array $queryParams = []): array
    {
        $params = array_merge(['value' => $this->leaderboardValueParam()], $queryParams);
        return parent::getLeaderboardData($params);
    }

    // -- Injected tests -- //

    public function test_only_latest_meta_counts(): void
    {
        $user = User::factory()->create();
        $map = $this->createValidMap();

        $completion = Completion::factory()->create(['map_code' => $map->code]);

        $validFactory = $this->validCompletionMetaFactory();
        $invalidFactory = $this->invalidCompletionMetaFactory();

        // We need to create metas with different states
        // First two are deleted (one valid, one invalid), third is valid
        for ($i = 0; $i < 3; $i++) {
            $factory = ($i === 2) ? $validFactory : $invalidFactory;
            $factory->for($completion)
                ->withPlayers([$user])
                ->accepted()
                ->create([
                    'format_id' => $this->formatId(),
                    'created_on' => now()->subDay()->addSeconds($i),
                ]);
        }

        $actual = $this->getLeaderboardData();

        $userEntry = collect($actual['data'])->firstWhere('user.discord_id', $user->discord_id);
        $this->assertNotNull($userEntry);
        $this->assertEquals(1, $userEntry['score']);
    }

    public function test_tied_scores_have_same_ranking(): void
    {
        [$user1, $user2, $user3] = User::factory()->count(3)->create();

        $maps = Map::factory()->count(5)->create();
        $remakeMap = RetroMap::factory()->create();
        MapListMeta::factory()
            ->count($maps->count())
            ->sequence(fn($seq) => [
                'code' => $maps[$seq->index],
                'remake_of' => $remakeMap->id,
            ])
            ->create();

        $comps = Completion::factory()
            ->count($maps->count())
            ->sequence(fn($seq) => ['map_code' => $maps[$seq->index]])
            ->create();
        $usersForMetas = [$user1, $user1, $user2, $user2, $user3];

        for ($i = 0; $i < $comps->count(); $i++) {
            $this->validCompletionMetaFactory()
                ->for($comps[$i])
                ->withPlayers([$usersForMetas[$i]])
                ->accepted()
                ->create(['format_id' => FormatConstants::NOSTALGIA_PACK]);
        }

        $actual = $this->getJson('/api/formats/' . FormatConstants::NOSTALGIA_PACK . '/leaderboard?value=' . $this->leaderboardValueParam())
            ->assertStatus(200)
            ->json();

        $this->assertEquals(1, $actual['data'][0]['placement']);
        $this->assertEquals(1, $actual['data'][1]['placement']);
        $this->assertEquals(3, $actual['data'][2]['placement']);
    }

    public function test_deleted_and_pending_with_valid(): void
    {
        $user = User::factory()->create();

        // Create 3 different maps for 3 completions
        $maps = [
            $this->createValidMap(),
            $this->createValidMap(),
            $this->createValidMap(),
        ];

        $completions = Completion::factory()
            ->count(3)
            ->sequence(fn(Sequence $seq) => [
                'map_code' => $maps[$seq->index]->code,
                'submitted_on' => now()->subSeconds(3 - $seq->index),
            ])
            ->create();

        // First: deleted, Second: pending, Third: accepted
        $states = [
            ['deleted_on' => now()],
            ['accepted_by_id' => null],
            ['accepted_by_id' => $user->discord_id],
        ];

        for ($i = 0; $i < 3; $i++) {
            $factory = $this->validCompletionMetaFactory()
                ->for($completions[$i])
                ->withPlayers([$user]);

            if ($i === 1) {
                $factory = $factory->pending();
            } else {
                $factory = $factory->accepted();
            }

            $factory->create([
                'format_id' => $this->formatId(),
                ...$states[$i],
            ]);
        }

        $actual = $this->getLeaderboardData();

        $userEntry = collect($actual['data'])->firstWhere('user.discord_id', $user->discord_id);
        $this->assertNotNull($userEntry);
        $this->assertEquals(1, $userEntry['score']);
    }

    public function test_standard_and_special_completions_count_independently(): void
    {
        $user = User::factory()->create();
        $map1 = $this->createValidMap();
        $map2 = $this->createValidMap();

        // Standard completion on map1
        $comp1 = Completion::factory()->create(['map_code' => $map1->code]);
        $this->invalidCompletionMetaFactory()
            ->for($comp1)
            ->withPlayers([$user])
            ->accepted()
            ->standard()
            ->create(['format_id' => $this->formatId()]);

        // Special completion on map2 (e.g., BB, no geraldo, LCC)
        $comp2 = Completion::factory()->create(['map_code' => $map2->code]);
        $this->validCompletionMetaFactory()
            ->for($comp2)
            ->withPlayers([$user])
            ->accepted()
            ->create(['format_id' => $this->formatId()]);

        $actual = $this->getLeaderboardData();

        $userEntry = collect($actual['data'])->firstWhere('user.discord_id', $user->discord_id);
        $this->assertNotNull($userEntry);
        $this->assertEquals(1, $userEntry['score']);
    }
}
