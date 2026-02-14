<?php

namespace Tests\Feature\Completions\List;

use App\Constants\FormatConstants;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\LeastCostChimps;
use App\Models\Map;
use App\Models\User;
use Database\Factories\CompletionMetaFactory;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;
use Illuminate\Database\Eloquent\Factories\Sequence;

class LccFilterTest extends ThreeStateFilterTestBase
{
    protected function getFilterName(): string
    {
        return 'lcc';
    }

    protected function createIncludedMetaFactory(): CompletionMetaFactory
    {
        $lcc = LeastCostChimps::factory()->create();
        return CompletionMeta::factory()->state(['lcc_id' => $lcc->id]);
    }

    protected function createExcludedMetaFactory(): CompletionMetaFactory
    {
        return CompletionMeta::factory()->state(['lcc_id' => null]);
    }

    protected function includedHasCurrentLcc(): bool
    {
        return true;
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('lcc')]
    public function test_is_current_lcc_true_for_largest_leftover(): void
    {
        $player = User::factory()->create();
        $map = Map::factory()->withMeta()->create();

        // Create two LCCs with different leftovers for the same map
        $lccSmall = LeastCostChimps::factory()->create(['leftover' => 1000]);
        $lccBig = LeastCostChimps::factory()->create(['leftover' => 5000]);

        // Create two completions with the LCCs on the same map
        $completionSmall = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()
            ->for($completionSmall)
            ->withPlayers([$player])
            ->accepted()
            ->create(['lcc_id' => $lccSmall->id, 'format_id' => FormatConstants::MAPLIST]);

        $completionBig = Completion::factory()->create(['map_code' => $map->code]);
        CompletionMeta::factory()
            ->for($completionBig)
            ->withPlayers([$player])
            ->accepted()
            ->create(['lcc_id' => $lccBig->id, 'format_id' => FormatConstants::MAPLIST]);

        // Get the actual response
        $actual = $this->getJson('/api/completions')
            ->assertStatus(200)
            ->json();

        // Find the completions in the response
        $completionSmallData = collect($actual['data'])->first(fn($item) => $item['id'] === $completionSmall->id);
        $completionBigData = collect($actual['data'])->first(fn($item) => $item['id'] === $completionBig->id);

        $this->assertIsArray($completionSmallData);
        $this->assertIsArray($completionBigData);
        $this->assertFalse($completionSmallData['is_current_lcc']);
        $this->assertTrue($completionBigData['is_current_lcc']);
    }
}
