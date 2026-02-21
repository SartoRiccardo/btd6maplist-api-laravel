<?php

namespace Tests\Abstract;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * Abstract Class TestsLeaderboardCommonBehavior
 *
 * Provides common leaderboard tests and helper methods.
 * The class must define formatId() and mapMetaKey() abstract methods.
 */
abstract class TestsLeaderboardCommonBehavior extends TestCase
{
    /**
     * The format ID to test (e.g., FormatConstants::MAPLIST)
     */
    abstract protected function formatId(): int;

    /**
     * The map meta key to use for map filtering (e.g., 'placement_curver', 'difficulty')
     */
    abstract protected function mapMetaKey(): string;

    /**
     * Map meta value for valid maps (default: 1)
     */
    protected function mapMetaValue(): int
    {
        return 1;
    }

    /**
     * Factory state for completion meta (e.g., 'standard', 'lcc', or null)
     */
    protected function completionMetaFactoryState(): ?string
    {
        return 'standard';
    }

    /**
     * Additional completion meta attributes to merge
     * (e.g., ['black_border' => true], ['no_geraldo' => true])
     */
    protected function completionMetaAttributes(): array
    {
        return [];
    }

    // -- Helper methods -- //

    /**
     * Create a map with valid metadata
     */
    protected function createValidMap(): Map
    {
        return Map::factory()->withMeta([$this->mapMetaKey() => $this->mapMetaValue()])->create();
    }

    /**
     * Create a completion with accepted meta for this leaderboard
     */
    protected function createAcceptedCompletion(Map $map, User $user, array $extraMeta = []): CompletionMeta
    {
        $completion = Completion::factory()->create(['map_code' => $map->code]);

        $metaAttributes = array_merge(
            ['format_id' => $this->formatId()],
            $this->completionMetaAttributes(),
            $extraMeta
        );

        $factory = CompletionMeta::factory()
            ->for($completion)
            ->withPlayers([$user])
            ->accepted();

        if ($this->completionMetaFactoryState() === 'standard') {
            $factory = $factory->standard();
        } elseif ($this->completionMetaFactoryState() === 'lcc') {
            $factory = $factory->lcc();
        }

        return $factory->create($metaAttributes);
    }

    /**
     * Get leaderboard data for the format
     */
    protected function getLeaderboardData(array $queryParams = []): array
    {
        $url = '/api/formats/' . $this->formatId() . '/leaderboard';
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        return $this->getJson($url)
            ->assertStatus(200)
            ->json();
    }

    /**
     * Get leaderboard data with the value parameter set
     * Override in child classes that need value-specific queries
     */
    protected function getLeaderboardDataWithValue(array $extraParams = []): array
    {
        return $this->getLeaderboardData($extraParams);
    }

    // -- Injected tests -- //

    #[Group('empty')]
    public function test_empty_leaderboard_returns_no_data(): void
    {
        $actual = $this->getLeaderboardData();

        $this->assertEquals([], $actual['data']);
    }

    #[Group('pagination')]
    public function test_page_out_of_bounds_returns_no_data(): void
    {
        $user = User::factory()->create();
        $map = $this->createValidMap();

        $this->createAcceptedCompletion($map, $user);

        $actual = $this->getLeaderboardData(['page' => 999]);

        $this->assertEmpty($actual['data']);
        $this->assertEquals(1, $actual['meta']['last_page']);
    }

    #[Group('validation')]
    public function test_page_must_be_positive(): void
    {
        $this->getJson("/api/formats/{$this->formatId()}/leaderboard?page=0")
            ->assertStatus(422);
    }

    #[Group('validation')]
    public function test_page_must_be_numeric(): void
    {
        $this->getJson("/api/formats/{$this->formatId()}/leaderboard?page=abc")
            ->assertStatus(422);
    }

    public function test_deleted_completion_not_counted(): void
    {
        $user = User::factory()->create();
        $map = $this->createValidMap();

        $completion = Completion::factory()->create(['map_code' => $map->code]);

        $metaAttributes = array_merge(
            ['format_id' => $this->formatId()],
            $this->completionMetaAttributes()
        );

        $factory = CompletionMeta::factory()
            ->for($completion)
            ->withPlayers([$user])
            ->accepted()
            ->deleted();

        if ($this->completionMetaFactoryState() === 'standard') {
            $factory = $factory->standard();
        } elseif ($this->completionMetaFactoryState() === 'lcc') {
            $factory = $factory->lcc();
        }

        $factory->create($metaAttributes);

        $actual = $this->getLeaderboardData();

        $userIds = array_column($actual['data'], 'user.discord_id');
        $this->assertNotContains($user->discord_id, $userIds);
    }

    public function test_pending_completion_not_counted(): void
    {
        $user = User::factory()->create();
        $map = $this->createValidMap();

        $completion = Completion::factory()->create(['map_code' => $map->code]);

        $metaAttributes = array_merge(
            ['format_id' => $this->formatId()],
            $this->completionMetaAttributes()
        );

        CompletionMeta::factory()
            ->for($completion)
            ->withPlayers([$user])
            ->pending()
            ->create($metaAttributes);

        $actual = $this->getLeaderboardData();

        $userIds = array_column($actual['data'], 'user.discord_id');
        $this->assertNotContains($user->discord_id, $userIds);
    }

    public function test_other_formats_completions_not_counted(): void
    {
        $user = User::factory()->create();
        $map = $this->createValidMap();

        $metaAttributes = array_merge(
            $this->completionMetaAttributes(),
            ['black_border' => true] // Ensure it has a flag if needed
        );

        // Create completions in OTHER formats
        $otherFormats = [
            FormatConstants::EXPERT_LIST,
            FormatConstants::NOSTALGIA_PACK,
        ];

        foreach ($otherFormats as $formatId) {
            if ($formatId === $this->formatId()) {
                continue;
            }

            $comp = Completion::factory()->create(['map_code' => $map->code]);
            CompletionMeta::factory()
                ->for($comp)
                ->withPlayers([$user])
                ->accepted()
                ->create(['format_id' => $formatId, ...$metaAttributes]);
        }

        $actual = $this->getLeaderboardData();

        $userIds = array_column($actual['data'], 'user.discord_id');
        $this->assertNotContains($user->discord_id, $userIds);
    }

    #[Group('validation')]
    public function test_invalid_value_parameter_returns_422(): void
    {
        $this->getJson("/api/formats/{$this->formatId()}/leaderboard?value=bananas")
            ->assertStatus(422);
    }

    #[Group('validation')]
    #[Group('pagination')]
    public function test_per_page_validation(): void
    {
        $this->getJson("/api/formats/{$this->formatId()}/leaderboard?per_page=0")
            ->assertStatus(422);

        $this->getJson("/api/formats/{$this->formatId()}/leaderboard?per_page=99999")
            ->assertStatus(422);
    }

    public function test_user_with_zero_completions_doesnt_appear(): void
    {
        $user = User::factory()->create();

        $actual = $this->getLeaderboardData();

        $userIds = array_column($actual['data'], 'user.discord_id');
        $this->assertNotContains($user->discord_id, $userIds);
    }

    public function test_tie_breaking_by_discord_id(): void
    {
        $user1 = User::factory()->create(['discord_id' => '111']);
        $user2 = User::factory()->create(['discord_id' => '222']);
        $map = $this->createValidMap();

        $completions = Completion::factory()
            ->count(2)
            ->sequence(fn(Sequence $seq) => [
                'submitted_on' => now()->subSeconds(1 - $seq->index),
            ])
            ->create(['map_code' => $map->code]);

        $metaAttributes = array_merge(
            $this->completionMetaAttributes()
        );

        $baseFactory = CompletionMeta::factory()
            ->withPlayers([$user1, $user2]);

        if ($this->completionMetaFactoryState() === 'standard') {
            $baseFactory = $baseFactory->standard();
        } elseif ($this->completionMetaFactoryState() === 'lcc') {
            $baseFactory = $baseFactory->lcc();
        }

        foreach ($completions as $index => $completion) {
            $baseFactory->for($completion)
                ->accepted()
                ->create([
                    'format_id' => $this->formatId(),
                    'accepted_by_id' => $index === 0 ? $user1->discord_id : $user2->discord_id,
                    ...$metaAttributes,
                ]);
        }

        $actual = $this->getLeaderboardData();

        // Sort order is desc by discord_id
        $this->assertEquals('222', $actual['data'][0]['user']['discord_id']);
        $this->assertEquals('111', $actual['data'][1]['user']['discord_id']);
    }

    #[Group('pagination')]
    public function test_pagination_per_page_1(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $map = $this->createValidMap();

        $completions = Completion::factory()
            ->count(2)
            ->sequence(fn(Sequence $seq) => [
                'submitted_on' => now()->subSeconds(1 - $seq->index),
            ])
            ->create(['map_code' => $map->code]);

        $metaAttributes = array_merge(
            $this->completionMetaAttributes()
        );

        $baseFactory = CompletionMeta::factory()
            ->withPlayers([$user1, $user2]);

        if ($this->completionMetaFactoryState() === 'standard') {
            $baseFactory = $baseFactory->standard();
        } elseif ($this->completionMetaFactoryState() === 'lcc') {
            $baseFactory = $baseFactory->lcc();
        }

        foreach ($completions as $index => $completion) {
            $baseFactory->for($completion)
                ->accepted()
                ->create([
                    'format_id' => $this->formatId(),
                    'accepted_by_id' => $index === 0 ? $user1->discord_id : $user2->discord_id,
                    ...$metaAttributes,
                ]);
        }

        $actual = $this->getLeaderboardData(['per_page' => 1]);

        $this->assertCount(1, $actual['data']);
        $this->assertEquals(2, $actual['meta']['last_page']);
    }
}
