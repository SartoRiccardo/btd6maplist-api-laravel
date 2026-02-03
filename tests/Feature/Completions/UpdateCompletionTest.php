<?php

namespace Tests\Feature\Completions;

use App\Models\CompPlayer;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\LeastCostChimps;
use App\Models\User;
use PHPUnit\Attributes\Group;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

class UpdateCompletionTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected Completion $completion;
    protected User $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->player = User::factory()->create();
        $completions = CompletionTestHelper::createCompletionsWithMeta(1, $this->player, accepted: true);
        $this->completion = $completions->first();
    }

    // -- TestsDiscordAuthMiddleware trait requirements -- //

    protected function endpoint(): string
    {
        return "/api/completions/{$this->completion->id}";
    }

    protected function method(): string
    {
        return 'PUT';
    }

    protected function requestData(): array
    {
        return [
            'user_ids' => [(string) $this->player->discord_id],
            'format' => 1,
            'black_border' => false,
            'no_geraldo' => false,
        ];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 204;
    }

    // -- Feature tests -- //

    #[Group('put')]
    public function test_update_completion_success(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);

        // Load current state before update
        $this->completion->load(['map.latestMeta', 'latestMeta', 'proofs']);
        $currentMeta = $this->completion->latestMeta;

        $payload = [
            ...$this->requestData(),
            'black_border' => true,
            'lcc' => ['leftover' => 5000],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id, $payload)
            ->assertStatus(204);

        // Verify via GET
        $actual = $this->getJson('/api/completions/' . $this->completion->id)
            ->assertStatus(200)
            ->json();

        $expected = CompletionTestHelper::expectedCompletionResponse(
            $currentMeta,
            $this->completion,
            $this->player,
            false
        );
        $expected = Completion::jsonStructure([...$expected, ...$payload]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('put')]
    public function test_update_completion_removing_lcc(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);
        $player = User::factory()->create();

        // Create completion with LCC
        $completion = Completion::factory()->create();
        $lcc = LeastCostChimps::factory()->create(['leftover' => 1000]);
        CompletionMeta::factory()
            ->withPlayers([$player])
            ->accepted()
            ->create([
                'completion_id' => $completion->id,
                'format_id' => 1,
                'lcc_id' => $lcc->id,
            ]);

        // Load current state before update
        $completion->load(['map.latestMeta', 'latestMeta', 'proofs']);

        $payload = [
            'user_ids' => [(string) $player->discord_id],
            'format' => 1,
            'black_border' => false,
            'no_geraldo' => false,
            'lcc' => null,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $completion->id, $payload)
            ->assertStatus(204);

        // Verify via GET
        $actual = $this->getJson('/api/completions/' . $completion->id)
            ->assertStatus(200)
            ->json();

        $expected = CompletionTestHelper::expectedCompletionResponse(
            $completion->latestMeta,
            $completion,
            $player,
            false
        );
        $expected = Completion::jsonStructure([...$expected, ...$payload]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('put')]
    public function test_update_own_completion_returns_forbidden(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);

        // Create completion where user is a player
        $completions = CompletionTestHelper::createCompletionsWithMeta(1, $user, accepted: true);
        $completion = $completions->first();

        $payload = [
            ...$this->requestData(),
            'user_ids' => [(string) $user->discord_id],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $completion->id, $payload)
            ->assertStatus(403)
            ->assertJson(['error' => 'Cannot edit your own completion']);
    }

    #[Group('put')]
    public function test_update_completion_without_permission_returns_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id, $this->requestData())
            ->assertStatus(403);
    }

    #[Group('put')]
    public function test_update_completion_with_scoped_permissions(): void
    {
        // Has edit:completion for format 51, not format 1
        $user = $this->createUserWithPermissions([51 => ['edit:completion']]);

        $payload = [
            ...$this->requestData(),
            'format' => 51,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id, $payload)
            ->assertStatus(403);
    }

    #[Group('put')]
    public function test_update_completion_format_51_without_permission_returns_forbidden(): void
    {
        // Has edit:completion for format 1, not format 51
        // Trying to change a format 51 completion to format 1
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);
        $player = User::factory()->create();

        // Create format 51 completion
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$player])
            ->accepted()
            ->create([
                'completion_id' => $completion->id,
                'format_id' => 51
            ]);

        $payload = [
            ...$this->requestData(),
            'user_ids' => [(string) $player->discord_id],
            'format' => 1,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $completion->id, $payload)
            ->assertStatus(403);
    }

    #[Group('put')]
    public function test_update_completion_with_invalid_user_ids(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);

        $payload = [
            ...$this->requestData(),
            'user_ids' => ['999999999'],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids.0']);
    }

    #[Group('put')]
    public function test_update_completion_nonexistent_returns_404(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/999999', $this->requestData())
            ->assertStatus(404);
    }

    #[Group('put')]
    public function test_update_completion_validation_errors_returns_422(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);

        $payload = [
            'user_ids' => ['not-an-id'],
            'format' => 'invalid',
            'black_border' => 'not-a-bool',
            'no_geraldo' => 'not-a-bool',
        ];

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id, $payload)
            ->assertStatus(422)
            ->json();

        $this->assertArrayHasKey('errors', $actual);
        $this->assertArrayHasKey('user_ids.0', $actual['errors']);
        $this->assertArrayHasKey('format', $actual['errors']);
        $this->assertArrayHasKey('black_border', $actual['errors']);
        $this->assertArrayHasKey('no_geraldo', $actual['errors']);
    }

    #[Group('put')]
    public function test_update_completion_missing_required_fields_returns_422(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id, [])
            ->assertStatus(422)
            ->json();

        $this->assertArrayHasKey('errors', $actual);
        $this->assertArrayHasKey('user_ids', $actual['errors']);
        $this->assertArrayHasKey('format', $actual['errors']);
        $this->assertArrayHasKey('black_border', $actual['errors']);
        $this->assertArrayHasKey('no_geraldo', $actual['errors']);
    }

    #[Group('put')]
    public function test_update_completion_with_invalid_lcc_format(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);

        $payload = [
            ...$this->requestData(),
            'lcc' => ['leftover' => 'not-a-number'],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lcc.leftover']);
    }
}
