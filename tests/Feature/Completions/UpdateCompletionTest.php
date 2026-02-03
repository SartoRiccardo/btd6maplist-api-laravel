<?php

namespace Tests\Feature\Completions;

use App\Models\Config;
use App\Models\CompPlayer;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Format;
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
    protected Format $format;

    protected function setUp(): void
    {
        parent::setUp();

        // Create config needed by VerificationService
        Config::firstOrCreate(['name' => 'current_btd6_ver'], [
            'value' => '45',
            'type' => 'int',
        ]);

        $this->format = Format::factory()->create();
        $this->player = User::factory()->create();
        $completions = CompletionTestHelper::createCompletionsWithMeta(
            1,
            $this->player,
            accepted: true,
            formatId: $this->format->id
        );
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
            'format' => $this->format->id,
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
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

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
            true  // LCC with leftover 5000 becomes the current LCC
        );
        $expected = Completion::jsonStructure([...$expected, ...$payload]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('put')]
    public function test_update_completion_removing_lcc(): void
    {
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);
        $player = User::factory()->create();

        // Create completion with LCC
        $completion = Completion::factory()->create();
        $lcc = LeastCostChimps::factory()->create(['leftover' => 1000]);
        CompletionMeta::factory()
            ->withPlayers([$player])
            ->accepted()
            ->create([
                'completion_id' => $completion->id,
                'format_id' => $this->format->id,
                'lcc_id' => $lcc->id,
            ]);

        // Load current state before update
        $completion->load(['map.latestMeta', 'latestMeta', 'proofs']);

        $payload = [
            'user_ids' => [(string) $player->discord_id],
            'format' => $this->format->id,
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
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

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
        // Has edit:completion for second format, not first format
        $secondFormat = Format::factory()->create();
        $user = $this->createUserWithPermissions([$secondFormat->id => ['edit:completion']]);

        $payload = [
            ...$this->requestData(),
            'format' => $secondFormat->id,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $this->completion->id, $payload)
            ->assertStatus(403);
    }

    #[Group('put')]
    public function test_update_completion_format_change_without_permission_returns_forbidden(): void
    {
        // Has edit:completion for first format, not second format
        // Trying to change a second format completion to first format
        $secondFormat = Format::factory()->create();
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);
        $player = User::factory()->create();

        // Create second format completion
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$player])
            ->accepted()
            ->create([
                'completion_id' => $completion->id,
                'format_id' => $secondFormat->id
            ]);

        $payload = [
            ...$this->requestData(),
            'user_ids' => [(string) $player->discord_id],
            'format' => $this->format->id,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/' . $completion->id, $payload)
            ->assertStatus(403);
    }

    #[Group('put')]
    public function test_update_completion_with_invalid_user_ids(): void
    {
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

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
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/999999', $this->requestData())
            ->assertStatus(404);
    }

    #[Group('put')]
    public function test_update_completion_validation_errors_returns_422(): void
    {
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

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
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

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
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

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
