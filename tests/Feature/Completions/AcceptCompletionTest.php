<?php

namespace Tests\Feature\Completions;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Config;
use App\Models\User;
use PHPUnit\Attributes\Group;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

class AcceptCompletionTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected Completion $completion;
    protected User $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->player = User::factory()->create();
        $completions = CompletionTestHelper::createCompletionsWithMeta(1, $this->player, accepted: false);
        $this->completion = $completions->first();
    }

    // -- TestsDiscordAuthMiddleware trait requirements -- //

    protected function endpoint(): string
    {
        return "/api/completions/{$this->completion->id}/accept";
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
    public function test_accept_completion_success(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);

        // Load current state before accept
        $this->completion->load(['map.latestMeta', 'latestMeta', 'proofs']);
        $currentMeta = $this->completion->latestMeta;

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id . '/accept', $this->requestData())
            ->assertStatus(204);

        // Verify it's accepted
        $actual = $this->getJson('/api/completions/' . $this->completion->id)
            ->assertStatus(200)
            ->json();

        $expected = CompletionTestHelper::expectedCompletionResponse(
            $currentMeta,
            $this->completion,
            $this->player,
            false
        );
        $expected = Completion::jsonStructure([...$expected, ...$this->requestData()]);
        $expected['accepted_by'] = (string) $user->discord_id;

        $this->assertEquals($expected, $actual);
    }

    #[Group('put')]
    public function test_accept_completion_creates_verifications_for_format_1(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);

        // Ensure config exists for verification
        Config::factory()->create([
            'name' => 'current_btd6_ver',
            'value' => '45',
            'type' => 'int',
        ]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id . '/accept', $this->requestData())
            ->assertStatus(204);

        // TODO: Rewrite to use GET /maps/{code}/verifications when endpoint exists
        $this->markTestIncomplete("Rewrite to use GET /maps/{code}/verifications when endpoint exists");

        // Verify verifications were created
        $this->assertDatabaseHas('verifications', [
            'map_code' => $this->completion->map_code,
            'user_id' => $this->player->discord_id,
        ]);
    }

    #[Group('put')]
    public function test_accept_completion_creates_verifications_for_format_51(): void
    {
        $user = $this->createUserWithPermissions([51 => ['edit:completion']]);
        $player = User::factory()->create();

        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$player])
            ->create([
                'completion_id' => $completion->id,
                'format_id' => 51,
            ]);

        Config::factory()->create([
            'name' => 'current_btd6_ver',
            'value' => '45',
            'type' => 'int',
        ]);

        $payload = [
            ...$this->requestData(),
            'user_ids' => [(string) $player->discord_id],
            'format' => 51,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $completion->id . '/accept', $payload)
            ->assertStatus(204);

        // TODO: Rewrite to use GET /maps/{code}/verifications when endpoint exists
        $this->markTestIncomplete("Rewrite to use GET /maps/{code}/verifications when endpoint exists");

        // Verify verifications were created
        $this->assertDatabaseHas('verifications', [
            'map_code' => $completion->map_code,
            'user_id' => $player->discord_id,
        ]);
    }

    #[Group('put')]
    public function test_accept_completion_already_accepted_returns_error(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id . '/accept', $this->requestData())
            ->assertStatus(204);

        // Try to accept again
        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id . '/accept', $this->requestData())
            ->assertStatus(400)
            ->assertJson(['error' => 'Completion already accepted']);
    }

    #[Group('put')]
    public function test_accept_own_completion_returns_forbidden(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);

        // Create completion where user is a player
        $completion = CompletionTestHelper::createCompletionsWithMeta(1, $user, accepted: false)
            ->first();

        $payload = [
            ...$this->requestData(),
            'user_ids' => [(string) $user->discord_id],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $completion->id . '/accept', $payload)
            ->assertStatus(403)
            ->assertJson(['error' => 'Cannot accept your own completion']);
    }

    #[Group('put')]
    public function test_accept_completion_without_permission_returns_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id . '/accept', $this->requestData())
            ->assertStatus(403);
    }

    #[Group('put')]
    public function test_accept_completion_with_scoped_permissions(): void
    {
        // Has edit:completion for format 51, not format 1
        $user = $this->createUserWithPermissions([51 => ['edit:completion']]);

        $payload = [
            ...$this->requestData(),
            'format' => 51,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id . '/accept', $payload)
            ->assertStatus(403);
    }

    #[Group('put')]
    public function test_accept_completion_nonexistent_returns_404(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/999999/accept', $this->requestData())
            ->assertStatus(404);
    }

    #[Group('put')]
    public function test_accept_completion_with_lcc(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);

        // Load current state before accept
        $this->completion->load(['map.latestMeta', 'latestMeta', 'proofs']);
        $currentMeta = $this->completion->latestMeta;

        $payload = [
            ...$this->requestData(),
            'lcc' => ['leftover' => 9999],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id . '/accept', $payload)
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
        $expected['accepted_by'] = (string) $user->discord_id;

        $this->assertEquals($expected, $actual);
    }

    #[Group('put')]
    public function test_accept_completion_format_1_without_permission_returns_forbidden(): void
    {
        // Has edit:completion for format 1, not format 51
        // Trying to accept a format 51 completion as format 1
        $user = $this->createUserWithPermissions([1 => ['edit:completion']]);
        $player = User::factory()->create();

        // Create format 51 completion
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$player])
            ->create([
                'completion_id' => $completion->id,
                'format_id' => 51,
            ]);

        $payload = [
            ...$this->requestData(),
            'user_ids' => [(string) $player->discord_id],
            'format' => 1,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $completion->id . '/accept', $payload)
            ->assertStatus(403);
    }

    #[Group('put')]
    public function test_accept_completion_maplist_all_versions_does_not_create_verifications(): void
    {
        $user = $this->createUserWithPermissions([2 => ['edit:completion']]);
        $player = User::factory()->create();

        // Create format 2 completion
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$player])
            ->create([
                'completion_id' => $completion->id,
                'format_id' => 2,
            ]);

        $payload = [
            ...$this->requestData(),
            'user_ids' => [(string) $player->discord_id],
            'format' => 2,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $completion->id . '/accept', $payload)
            ->assertStatus(204);

        $this->markTestIncomplete("Rewrite to use GET /maps/{code}/verifications when endpoint exists");

        // Verify NO verifications were created
        $this->assertDatabaseMissing('verifications', [
            'map_code' => $completion->map_code,
            'user_id' => $player->discord_id,
        ]);
    }
}
