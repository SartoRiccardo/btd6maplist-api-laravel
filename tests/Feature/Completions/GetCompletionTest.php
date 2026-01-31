<?php

namespace Tests\Feature\Completions;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\CompletionProof;
use App\Models\Leastcostchimps;
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

        $expected = Completion::jsonStructure([
            ...$meta->toArray(),
            ...$completion->toArray(),
            'users' => [
                ['id' => (string) $user->discord_id, 'name' => $user->name],
            ],
            'current_lcc' => false,
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    public function test_get_completion_includes_lcc_data(): void
    {
        $user = User::factory()->create();
        $lcc = Leastcostchimps::factory()->create();

        $completion = Completion::factory()->create();
        $meta = CompletionMeta::factory()
            ->for($completion)
            ->accepted()
            ->withPlayers([$user])
            ->create(['lcc_id' => $lcc->id]);

        $actual = $this->getJson('/api/completions/' . $completion->id)
            ->assertStatus(200)
            ->json();

        $expected = Completion::jsonStructure([
            ...$meta->toArray(),
            ...$completion->toArray(),
            'users' => [
                ['id' => (string) $user->discord_id, 'name' => $user->name],
            ],
            'current_lcc' => true,
        ]);
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

        CompletionProof::factory()->image()->create(['run' => $completion->id]);
        CompletionProof::factory()->video()->create(['run' => $completion->id]);
        $completion->refresh();

        $actual = $this->getJson('/api/completions/' . $completion->id)
            ->assertStatus(200)
            ->json();

        $expected = Completion::jsonStructure([
            ...$meta->toArray(),
            ...$completion->toArray(),
            'users' => [
                ['id' => (string) $user->discord_id, 'name' => $user->name],
            ],
            'current_lcc' => false,
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    public function test_get_completion_returns_404_for_nonexistent_completion(): void
    {
        $this->getJson('/api/completions/999999')
            ->assertStatus(404)
            ->assertJson(['error' => 'Completion not found']);
    }
}
