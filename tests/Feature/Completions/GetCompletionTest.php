<?php

namespace Tests\Feature\Completions;

use App\Models\CompPlayer;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\CompletionProof;
use App\Models\LeastCostChimps;
use App\Models\Map;
use App\Models\User;
use PHPUnit\Attributes\Group;
use Tests\TestCase;

class GetCompletionTest extends TestCase
{
    #[Group('get')]
    public function test_get_completion_returns_valid_completion(): void
    {
        $user = User::factory()->create();
        $completion = Completion::factory()->create();
        $meta = CompletionMeta::factory()
            ->accepted($user->discord_id)
            ->withPlayers([$user])
            ->create(['completion_id' => $completion->id]);

        $actual = $this->getJson('/api/completions/' . $completion->id)
            ->assertStatus(200)
            ->json();

        $expected = $this->expectedCompletionResponse($meta, $completion, $user, false);
        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    public function test_get_completion_includes_lcc_data(): void
    {
        $user = User::factory()->create();
        $lcc = LeastCostChimps::factory()->create();

        $completion = Completion::factory()->create();
        $meta = CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->withPlayers([$user])
            ->create(['lcc_id' => $lcc->id]);

        $actual = $this->getJson('/api/completions/' . $completion->id)
            ->assertStatus(200)
            ->json();

        $expected = $this->expectedCompletionResponse($meta, $completion, $user, true);
        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    public function test_get_completion_includes_proofs(): void
    {
        $user = User::factory()->create();
        $completion = Completion::factory()->create();
        $meta = CompletionMeta::factory()
            ->accepted()
            ->withPlayers([$user])
            ->create(['completion_id' => $completion->id]);

        $proofImg = CompletionProof::factory()->image()->create(['run' => $completion->id]);
        $proofVid = CompletionProof::factory()->video()->create(['run' => $completion->id]);
        $completion->refresh();

        $actual = $this->getJson('/api/completions/' . $completion->id)
            ->assertStatus(200)
            ->json();

        $this->assertEquals([$proofImg->proof_url], $actual['subm_proof_img']);
        $this->assertEquals([$proofVid->proof_url], $actual['subm_proof_vid']);
    }

    #[Group('get')]
    public function test_get_completion_returns_404_for_nonexistent_completion(): void
    {
        $this->getJson('/api/completions/999999')
            ->assertStatus(404)
            ->assertJson(['error' => 'Completion not found']);
    }

    #[Group('get')]
    public function test_get_completion_returns_latest_meta_when_multiple_exist(): void
    {
        $user = User::factory()->create();
        $completion = Completion::factory()->create();

        $metaCount = 3;
        $metas = CompletionMeta::factory()
            ->count($metaCount)
            ->for($completion)
            ->accepted($user->discord_id)
            ->withPlayers([$user])
            ->sequence(fn($seq) => [
                'created_on' => now()->subDays(2 - $seq->index),
            ])
            ->create();

        $actual = $this->getJson('/api/completions/' . $completion->id)
            ->assertStatus(200)
            ->json();

        $expected = $this->expectedCompletionResponse($metas[$metaCount - 1], $completion, $user, false);
        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    public function test_get_completion_returns_latest_meta_even_if_deleted(): void
    {
        $user = User::factory()->create();
        $completion = Completion::factory()->create();

        // Create three metas at different times, latest one is deleted
        $metaCount = 3;
        $metas = CompletionMeta::factory()
            ->count($metaCount)
            ->for($completion)
            ->accepted($user->discord_id)
            ->withPlayers([$user])
            ->sequence(fn($seq) => [
                'deleted_on' => $seq->index === $metaCount - 1 ? now() : null,
                'created_on' => now()->subDays($metaCount - $seq->index),
            ])
            ->create();

        $actual = $this->getJson('/api/completions/' . $completion->id)
            ->assertStatus(200)
            ->json();

        // Should return the latest (deleted) meta
        $expected = $this->expectedCompletionResponse($metas[$metaCount - 1], $completion, $user, false);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Build expected completion response structure.
     */
    protected function expectedCompletionResponse(
        CompletionMeta $meta,
        Completion $completion,
        User $user,
        bool $currentLcc
    ): array {
        return Completion::jsonStructure([
            ...$meta->toArray(),
            ...$completion->toArray(),
            'map' => Map::jsonStructure([
                ...$completion->map->latestMeta->toArray(),
                ...$completion->map->toArray(),
            ]),
            'users' => [
                ['id' => (string) $user->discord_id, 'name' => $user->name],
            ],
            'current_lcc' => $currentLcc,
        ]);
    }
}
