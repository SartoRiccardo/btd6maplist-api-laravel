<?php

namespace Tests\Feature\Formats\Leaderboard;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\MapListMeta;
use App\Models\RetroMap;
use App\Models\User;
use Tests\Traits\TestsLeaderboardCommonBehavior;
use Tests\TestCase;
use Illuminate\Database\Eloquent\Factories\Sequence;

#[Group('get')]
#[Group('formats')]
#[Group('leaderboard')]
#[Group('blackborder')]
class BlackBorderTest extends TestCase
{
    use TestsLeaderboardCommonBehavior;

    protected function formatId(): int
    {
        return FormatConstants::MAPLIST;
    }

    protected function mapMetaKey(): string
    {
        return 'placement_curver';
    }

    protected function completionMetaFactoryState(): ?string
    {
        return null;
    }

    protected function completionMetaAttributes(): array
    {
        return ['black_border' => true];
    }

    public function test_three_bb_completions_same_map_counted_once(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['placement_curver' => 1])->create();

        $completions = Completion::factory()
            ->count(3)
            ->sequence(fn(Sequence $seq) => [
                'submitted_on' => now()->subSeconds(2 - $seq->index),
            ])
            ->create(['map_code' => $map->code]);

        CompletionMeta::factory()
            ->count(count($completions))
            ->sequence(fn(Sequence $seq) => [
                'completion_id' => $completions[$seq->index]->id,
                'format_id' => FormatConstants::MAPLIST,
            ])
            ->accepted($user->discord_id)
            ->withPlayers([$user])
            ->create(['black_border' => true]);

        $actual = $this->getJson('/api/formats/1/leaderboard?value=black_border')
            ->assertStatus(200)
            ->json();

        $userEntry = collect($actual['data'])->firstWhere('user.discord_id', $user->discord_id);
        $this->assertNotNull($userEntry);
        $this->assertEquals(1, $userEntry['score']);
    }

    public function test_only_latest_meta_counts(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['difficulty' => 1])->create();

        $completion = Completion::factory()->create(['map_code' => $map->code]);

        $metas = [
            [
                'format_id' => FormatConstants::EXPERT_LIST,
                'black_border' => true,
                'deleted_on' => now(),
            ],
            [
                'format_id' => FormatConstants::EXPERT_LIST,
                'black_border' => false,
                'deleted_on' => now(),
            ],
            [
                'format_id' => FormatConstants::EXPERT_LIST,
                'black_border' => true,
            ],
        ];

        CompletionMeta::factory()
            ->for($completion)
            ->withPlayers([$user])
            ->accepted()
            ->count(count($metas))
            ->sequence(fn(Sequence $seq) => [
                ...$metas[$seq->index],
                'created_on' => now()->subDay()->addSeconds($seq->index),
            ])
            ->create();

        $actual = $this->getJson('/api/formats/' . FormatConstants::EXPERT_LIST . '/leaderboard?value=black_border')
            ->assertStatus(200)
            ->json();

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
            CompletionMeta::factory()
                ->for($comps[$i])
                ->withPlayers([$usersForMetas[$i]])
                ->accepted()
                ->create(['format_id' => FormatConstants::NOSTALGIA_PACK, 'black_border' => true]);
        }

        $actual = $this->getJson('/api/formats/' . FormatConstants::NOSTALGIA_PACK . '/leaderboard?value=black_border')
            ->assertStatus(200)
            ->json();

        $this->assertEquals(1, $actual['data'][0]['placement']);
        $this->assertEquals(1, $actual['data'][1]['placement']);
        $this->assertEquals(3, $actual['data'][2]['placement']);
    }

    public function test_deleted_and_pending_on_same_map_with_valid(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['placement_curver' => 1])->create();

        $completions = Completion::factory()
            ->count(3)
            ->sequence(fn(Sequence $seq) => [
                'submitted_on' => now()->subSeconds(3 - $seq->index),
            ])
            ->create(['map_code' => $map->code]);

        CompletionMeta::factory()
            ->count(count($completions))
            ->sequence(fn(Sequence $seq) => [
                'completion_id' => $completions[$seq->index]->id,
                'format_id' => FormatConstants::MAPLIST,
                'black_border' => true,
            ])
            ->sequence(fn(Sequence $seq) => [
                'accepted_by_id' => $seq->index === 2 ? $user->discord_id : null,
                'deleted_on' => $seq->index === 0 ? now() : null,
            ])
            ->withPlayers([$user])
            ->create();

        $actual = $this->getJson('/api/formats/1/leaderboard?value=black_border')
            ->assertStatus(200)
            ->json();

        $userEntry = collect($actual['data'])->firstWhere('user.discord_id', $user->discord_id);
        $this->assertNotNull($userEntry);
        $this->assertEquals(1, $userEntry['score']);
    }

    public function test_standard_and_bb_completions_count_independently(): void
    {
        $user = User::factory()->create();
        $map1 = Map::factory()->withMeta(['placement_curver' => 1])->create();
        $map2 = Map::factory()->withMeta(['placement_curver' => 2])->create();

        // Standard completion on map1
        $comp1 = Completion::factory()->create(['map_code' => $map1->code]);
        CompletionMeta::factory()
            ->for($comp1)
            ->withPlayers([$user])
            ->accepted()
            ->standard()
            ->create(['format_id' => FormatConstants::MAPLIST, 'black_border' => false]);

        // BB completion on map2
        $comp2 = Completion::factory()->create(['map_code' => $map2->code]);
        CompletionMeta::factory()
            ->for($comp2)
            ->withPlayers([$user])
            ->accepted()
            ->create(['format_id' => FormatConstants::MAPLIST, 'black_border' => true]);

        $actual = $this->getJson('/api/formats/1/leaderboard?value=black_border')
            ->assertStatus(200)
            ->json();

        $userEntry = collect($actual['data'])->firstWhere('user.discord_id', $user->discord_id);
        $this->assertNotNull($userEntry);
        $this->assertEquals(1, $userEntry['score']);
    }
}
