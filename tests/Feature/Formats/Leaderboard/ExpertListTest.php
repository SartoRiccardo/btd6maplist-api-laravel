<?php

namespace Tests\Feature\Formats\Leaderboard;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Map;
use App\Models\User;
use Tests\Helpers\LeaderboardTestHelper;
use Tests\TestCase;
use Illuminate\Database\Eloquent\Factories\Sequence;

#[Group('get')]
#[Group('formats')]
#[Group('leaderboard')]
#[Group('expertlist')]
class ExpertListTest extends TestCase
{
    protected LeaderboardTestHelper $lbHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lbHelper = new LeaderboardTestHelper($this);
    }

    #[Group('empty')]
    public function test_empty_leaderboard_returns_no_data(): void
    {
        $format = FormatConstants::EXPERT_LIST;

        $actual = $this->getJson("/api/formats/{$format}/leaderboard")
            ->assertStatus(200)
            ->json();

        $this->assertEquals([], $actual['data']);
    }

    #[Group('pagination')]
    public function test_page_out_of_bounds_returns_no_data(): void
    {
        $user = User::factory()->create();
        $formatId = FormatConstants::EXPERT_LIST;

        $map = Map::factory()->withMeta(['difficulty' => 0])->create();
        $completion = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()
            ->for($completion)
            ->withPlayers([$user])
            ->accepted()
            ->create(['format_id' => $formatId]);

        $actual = $this->getJson("/api/formats/{$formatId}/leaderboard?page=999")
            ->assertStatus(200)
            ->json();

        $this->assertEmpty($actual['data']);
        $this->assertEquals(1, $actual['meta']['last_page']);
    }

    #[Group('validation')]
    public function test_page_must_be_positive(): void
    {
        $format = FormatConstants::EXPERT_LIST;

        $this->getJson("/api/formats/{$format}/leaderboard?page=0")
            ->assertStatus(422);
    }

    #[Group('validation')]
    public function test_page_must_be_numeric(): void
    {
        $format = FormatConstants::EXPERT_LIST;

        $this->getJson("/api/formats/{$format}/leaderboard?page=abc")
            ->assertStatus(422);
    }

    public function test_three_completions_same_map_no_modifiers_counted_once(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['difficulty' => 0])->create();

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
        $map = Map::factory()->withMeta(['difficulty' => 0])->create();

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
        $map = Map::factory()->withMeta(['difficulty' => 0])->create();

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
        $map = Map::factory()->withMeta(['difficulty' => 0])->create();

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
        $map = Map::factory()->withMeta(['difficulty' => 0])->create();

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
        $map = Map::factory()->withMeta(['difficulty' => 0])->create();

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
        $map = Map::factory()->withMeta(['difficulty' => 0])->create();

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

    public function test_deleted_completion_not_counted(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['difficulty' => 0])->create();

        $completion = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()
            ->for($completion)
            ->withPlayers([$user])
            ->accepted()
            ->deleted()
            ->create(['format_id' => FormatConstants::EXPERT_LIST]);

        $actual = $this->getJson('/api/formats/51/leaderboard')
            ->assertStatus(200)
            ->json();

        $userIds = array_column($actual['data'], 'user.discord_id');
        $this->assertNotContains($user->discord_id, $userIds);
    }

    public function test_pending_completion_not_counted(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['difficulty' => 0])->create();

        $completion = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()
            ->for($completion)
            ->withPlayers([$user])
            ->pending()
            ->create(['format_id' => FormatConstants::EXPERT_LIST]);

        $actual = $this->getJson('/api/formats/51/leaderboard')
            ->assertStatus(200)
            ->json();

        $userIds = array_column($actual['data'], 'user.discord_id');
        $this->assertNotContains($user->discord_id, $userIds);
    }

    public function test_maplist_completions_not_counted_for_expert_list(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['difficulty' => 0, 'placement_curver' => 1])->create();

        $comp1 = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()
            ->for($comp1)
            ->withPlayers([$user])
            ->accepted()
            ->create(['format_id' => FormatConstants::MAPLIST]);

        $comp2 = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()
            ->for($comp2)
            ->withPlayers([$user])
            ->accepted()
            ->create(['format_id' => FormatConstants::NOSTALGIA_PACK]);

        $actual = $this->getJson('/api/formats/51/leaderboard')
            ->assertStatus(200)
            ->json();

        $userIds = array_column($actual['data'], 'user.discord_id');
        $this->assertNotContains($user->discord_id, $userIds);
    }

    #[Group('validation')]
    public function test_invalid_value_parameter_returns_422(): void
    {
        $format = FormatConstants::EXPERT_LIST;

        $this->getJson("/api/formats/{$format}/leaderboard?value=bananas")
            ->assertStatus(422);
    }

    #[Group('validation')]
    #[Group('pagination')]
    public function test_per_page_validation(): void
    {
        $format = FormatConstants::EXPERT_LIST;

        $this->getJson("/api/formats/{$format}/leaderboard?per_page=0")
            ->assertStatus(422);

        $this->getJson("/api/formats/{$format}/leaderboard?per_page=99999")
            ->assertStatus(422);
    }

    public function test_user_with_zero_completions_doesnt_appear(): void
    {
        $user = User::factory()->create();

        $actual = $this->getJson('/api/formats/51/leaderboard')
            ->assertStatus(200)
            ->json();

        $userIds = array_column($actual['data'], 'user.discord_id');
        $this->assertNotContains($user->discord_id, $userIds);
    }

    public function test_tie_breaking_by_discord_id(): void
    {
        $user1 = User::factory()->create(['discord_id' => '111']);
        $user2 = User::factory()->create(['discord_id' => '222']);
        $map = Map::factory()->withMeta(['difficulty' => 0])->create();

        $completions = Completion::factory()
            ->count(2)
            ->sequence(fn(Sequence $seq) => [
                'submitted_on' => now()->subSeconds(1 - $seq->index),
            ])
            ->create(['map_code' => $map->code]);

        CompletionMeta::factory()
            ->count(count($completions))
            ->standard()
            ->withPlayers([$user1, $user2])
            ->sequence(fn(Sequence $seq) => [
                'completion_id' => $completions[$seq->index]->id,
                'format_id' => FormatConstants::EXPERT_LIST,
                'accepted_by_id' => $seq->index === 0 ? $user1->discord_id : $user2->discord_id,
            ])
            ->create();

        $actual = $this->getJson('/api/formats/51/leaderboard')
            ->assertStatus(200)
            ->json();

        // Sort order is desc
        $this->assertEquals('222', $actual['data'][0]['user']['discord_id']);
        $this->assertEquals('111', $actual['data'][1]['user']['discord_id']);
    }

    public function test_deleted_and_pending_on_same_map_with_valid(): void
    {
        $user = User::factory()->create();
        $map = Map::factory()->withMeta(['difficulty' => 0])->create();

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

    #[Group('pagination')]
    public function test_pagination_per_page_1(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $map = Map::factory()->withMeta(['difficulty' => 0])->create();

        $completions = Completion::factory()
            ->count(2)
            ->sequence(fn(Sequence $seq) => [
                'submitted_on' => now()->subSeconds(1 - $seq->index),
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
                'accepted_by_id' => $seq->index === 0 ? $user1->discord_id : $user2->discord_id,
            ])
            ->withPlayers([$user1, $user2])
            ->create();

        $actual = $this->getJson('/api/formats/51/leaderboard?per_page=1')
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $actual['data']);
        $this->assertEquals(2, $actual['meta']['last_page']);
    }
}
