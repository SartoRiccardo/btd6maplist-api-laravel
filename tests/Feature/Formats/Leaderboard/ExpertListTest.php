<?php

namespace Tests\Feature\Formats\Leaderboard;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\User;
use Tests\Helpers\LeaderboardTestHelper;
use Tests\Traits\TestsLeaderboardCommonBehavior;
use Tests\TestCase;
use Illuminate\Database\Eloquent\Factories\Sequence;

#[Group('get')]
#[Group('formats')]
#[Group('leaderboard')]
#[Group('expertlist')]
class ExpertListTest extends TestCase
{
    use TestsLeaderboardCommonBehavior;

    protected LeaderboardTestHelper $lbHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lbHelper = new LeaderboardTestHelper($this);
    }

    protected function formatId(): int
    {
        return FormatConstants::EXPERT_LIST;
    }

    protected function mapMetaKey(): string
    {
        return 'difficulty';
    }

    public function test_three_completions_same_map_no_modifiers_counted_once(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['difficulty' => 3])->create();

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
                'format_id' => FormatConstants::EXPERT_LIST,
            ])
            ->accepted($user->discord_id)
            ->standard()
            ->withPlayers([$user])
            ->create();

        $expectedPoints = $this->lbHelper->calcExpUserPoints($user->discord_id);
        $actualPoints = $this->lbHelper->getLbScore($user->discord_id, FormatConstants::EXPERT_LIST, 'points');

        $this->assertEqualsWithDelta($expectedPoints, $actualPoints, 0.0001);
    }

    public function test_lcc_modifier_applied(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['difficulty' => 1])->create();

        $completion = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()
            ->for($completion)
            ->withPlayers([$user])
            ->accepted()
            ->lcc()
            ->create(['format_id' => FormatConstants::EXPERT_LIST]);

        $expectedPoints = $this->lbHelper->calcExpUserPoints($user->discord_id);
        $actualPoints = $this->lbHelper->getLbScore($user->discord_id, FormatConstants::EXPERT_LIST, 'points');

        $this->assertEqualsWithDelta($expectedPoints, $actualPoints, 0.0001);
    }

    public function test_no_geraldo_modifier_applied(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['difficulty' => 4])->create();

        $completion = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()
            ->for($completion)
            ->withPlayers([$user])
            ->accepted()
            ->create(['format_id' => FormatConstants::EXPERT_LIST, 'no_geraldo' => true]);

        $expectedPoints = $this->lbHelper->calcExpUserPoints($user->discord_id);
        $actualPoints = $this->lbHelper->getLbScore($user->discord_id, FormatConstants::EXPERT_LIST, 'points');

        $this->assertEqualsWithDelta($expectedPoints, $actualPoints, 0.0001);
    }

    public function test_black_border_modifier_applied(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['difficulty' => 2])->create();

        $completion = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()
            ->for($completion)
            ->withPlayers([$user])
            ->accepted()
            ->create(['format_id' => FormatConstants::EXPERT_LIST, 'black_border' => true]);

        $expectedPoints = $this->lbHelper->calcExpUserPoints($user->discord_id);
        $actualPoints = $this->lbHelper->getLbScore($user->discord_id, FormatConstants::EXPERT_LIST, 'points');

        $this->assertEqualsWithDelta($expectedPoints, $actualPoints, 0.0001);
    }

    public function test_bb_and_ng_same_completion_multipliers(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['difficulty' => 3])->create();

        $completion = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()
            ->for($completion)
            ->withPlayers([$user])
            ->accepted()
            ->create([
                'format_id' => FormatConstants::EXPERT_LIST,
                'black_border' => true,
                'no_geraldo' => true,
            ]);

        $expectedPoints = $this->lbHelper->calcExpUserPoints($user->discord_id);
        $actualPoints = $this->lbHelper->getLbScore($user->discord_id, FormatConstants::EXPERT_LIST, 'points');

        $this->assertEqualsWithDelta($expectedPoints, $actualPoints, 0.0001);
    }

    public function test_bb_and_ng_different_completions_multipliers(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['difficulty' => 4])->create();

        $completions = Completion::factory()
            ->count(2)
            ->sequence(fn(Sequence $seq) => [
                'submitted_on' => now()->subSeconds(1 - $seq->index),
            ])
            ->create(['map_code' => $map->code]);

        CompletionMeta::factory()
            ->count(count($completions))
            ->standard()
            ->sequence(fn(Sequence $seq) => [
                'completion_id' => $completions[$seq->index]->id,
                'format_id' => FormatConstants::EXPERT_LIST,
                'black_border' => $seq->index === 0,
                'no_geraldo' => $seq->index === 1,
            ])
            ->accepted($user->discord_id)
            ->withPlayers([$user])
            ->create();

        $expectedPoints = $this->lbHelper->calcExpUserPoints($user->discord_id);
        $actualPoints = $this->lbHelper->getLbScore($user->discord_id, FormatConstants::EXPERT_LIST, 'points');

        $this->assertEqualsWithDelta($expectedPoints, $actualPoints, 0.0001);
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
                'no_geraldo' => true,
                'deleted_on' => now(),
            ],
            ['format_id' => FormatConstants::EXPERT_LIST],
        ];

        CompletionMeta::factory()
            ->for($completion)
            ->withPlayers([$user])
            ->accepted()
            ->standard()
            ->count(count($metas))
            ->sequence(fn(Sequence $seq) => [
                ...$metas[$seq->index],
                'created_on' => now()->subDay()->addSeconds($seq->index),
            ])
            ->create();

        $expectedPoints = $this->lbHelper->calcExpUserPoints($user->discord_id);
        $actualPoints = $this->lbHelper->getLbScore($user->discord_id, FormatConstants::EXPERT_LIST, 'points');

        $this->assertEqualsWithDelta($expectedPoints, $actualPoints, 0.0001);
    }

    public function test_tied_scores_have_same_ranking(): void
    {
        [$user1, $user2, $user3] = User::factory()->count(3)->create();

        $map1 = Map::factory()->withMeta(['difficulty' => 4])->create();
        $map2 = Map::factory()->withMeta(['difficulty' => 0])->create();

        [$comp1, $comp2, $comp3] = Completion::factory()
            ->count(3)
            ->sequence(
                ['map_code' => $map1->code],
                ['map_code' => $map1->code],
                ['map_code' => $map2->code],
            )
            ->create();

        $baseMetaFactory = CompletionMeta::factory()
            ->state(['format_id' => FormatConstants::EXPERT_LIST])
            ->for($comp1)
            ->accepted()
            ->standard();

        $baseMetaFactory->for($comp1)
            ->withPlayers([$user1])
            ->create();

        $baseMetaFactory->for($comp2)
            ->withPlayers([$user2])
            ->create();

        $baseMetaFactory->for($comp3)
            ->withPlayers([$user3])
            ->create();

        $actual = $this->getJson('/api/formats/51/leaderboard')
            ->assertStatus(200)
            ->json();

        $this->assertEquals(1, $actual['data'][0]['placement']);
        $this->assertEquals(1, $actual['data'][1]['placement']);
        $this->assertEquals(3, $actual['data'][2]['placement']);
    }

    public function test_deleted_and_pending_on_same_map_with_valid(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['difficulty' => 2])->create();

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
                'format_id' => FormatConstants::EXPERT_LIST,
            ])
            ->standard()
            ->sequence(fn(Sequence $seq) => [
                'accepted_by_id' => $seq->index === 2 ? $user->discord_id : null,
                'black_border' => $seq->index === 0,
                'no_geraldo' => $seq->index === 1,
                'deleted_on' => $seq->index === 0 ? now() : null,
            ])
            ->withPlayers([$user])
            ->create();

        $expectedPoints = $this->lbHelper->calcExpUserPoints($user->discord_id);
        $actualPoints = $this->lbHelper->getLbScore($user->discord_id, FormatConstants::EXPERT_LIST, 'points');

        $this->assertEqualsWithDelta($expectedPoints, $actualPoints, 0.0001);
    }
}
