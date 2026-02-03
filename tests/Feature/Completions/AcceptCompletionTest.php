<?php

namespace Tests\Feature\Completions;

use App\Constants\FormatConstants;
use App\Jobs\UpdateDiscordWebhookJob;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Config;
use App\Models\Format;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Attributes\Group;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

class AcceptCompletionTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected Completion $completion;
    protected User $player;
    protected Format $format;

    protected function setUp(): void
    {
        parent::setUp();

        $this->format = Format::factory()->create();
        $this->player = User::factory()->create();

        $completions = CompletionTestHelper::createCompletionsWithMeta(
            1,
            $this->player,
            accepted: false,
            formatId: $this->format->id
        );
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
    public function test_accept_completion_success(): void
    {
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

        // Load current state before accept
        $this->completion->load(['map.latestMeta', 'latestMeta', 'proofs']);
        $currentMeta = $this->completion->latestMeta;

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$this->completion->id}/accept", $this->requestData())
            ->assertStatus(204);

        // Verify it's accepted
        $actual = $this->getJson("/api/completions/{$this->completion->id}")
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
    public function test_accept_completion_creates_verifications_for_default_format(): void
    {
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

        // Ensure config exists for verification
        Config::factory()->create([
            'name' => 'current_btd6_ver',
            'value' => '45',
            'type' => 'int',
        ]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$this->completion->id}/accept", $this->requestData())
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
    public function test_accept_completion_creates_verifications_for_secondary_format(): void
    {
        $secondFormat = Format::factory()->create();
        $user = $this->createUserWithPermissions([$secondFormat->id => ['edit:completion']]);
        $player = User::factory()->create();

        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$player])
            ->create([
                'completion_id' => $completion->id,
                'format_id' => $secondFormat->id,
            ]);

        Config::factory()->create([
            'name' => 'current_btd6_ver',
            'value' => '45',
            'type' => 'int',
        ]);

        $payload = [
            ...$this->requestData(),
            'user_ids' => [(string) $player->discord_id],
            'format' => $secondFormat->id,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}/accept", $payload)
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
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$this->completion->id}/accept", $this->requestData())
            ->assertStatus(204);

        // Try to accept again
        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$this->completion->id}/accept", $this->requestData())
            ->assertStatus(400)
            ->assertJson(['error' => 'Completion already accepted']);
    }

    #[Group('put')]
    public function test_accept_own_completion_returns_forbidden(): void
    {
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

        // Create completion where user is a player
        $completion = CompletionTestHelper::createCompletionsWithMeta(
            1,
            $user,
            accepted: false,
            formatId: $this->format->id
        )->first();

        $payload = [
            ...$this->requestData(),
            'user_ids' => [(string) $user->discord_id],
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}/accept", $payload)
            ->assertStatus(403)
            ->assertJson(['error' => 'Cannot accept your own completion']);
    }

    #[Group('put')]
    public function test_accept_completion_without_permission_returns_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$this->completion->id}/accept", $this->requestData())
            ->assertStatus(403);
    }

    #[Group('put')]
    public function test_accept_completion_with_scoped_permissions(): void
    {
        // Has edit:completion for second format, not first format
        $secondFormat = Format::factory()->create();
        $user = $this->createUserWithPermissions([$secondFormat->id => ['edit:completion']]);

        $payload = [
            ...$this->requestData(),
            'format' => $secondFormat->id,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$this->completion->id}/accept", $payload)
            ->assertStatus(403);
    }

    #[Group('put')]
    public function test_accept_completion_nonexistent_returns_404(): void
    {
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/completions/999999/accept', $this->requestData())
            ->assertStatus(404);
    }

    #[Group('put')]
    public function test_accept_completion_with_lcc(): void
    {
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

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
        $actual = $this->getJson("/api/completions/{$this->completion->id}")
            ->assertStatus(200)
            ->json();

        $expected = CompletionTestHelper::expectedCompletionResponse(
            $currentMeta,
            $this->completion,
            $this->player,
            true  // LCC with leftover 9999 becomes the current LCC
        );
        $expected = Completion::jsonStructure([...$expected, ...$payload]);
        $expected['accepted_by'] = (string) $user->discord_id;

        $this->assertEquals($expected, $actual);
    }

    #[Group('put')]
    public function test_accept_completion_format_change_without_permission_returns_forbidden(): void
    {
        // Has edit:completion for first format, not second format
        // Trying to accept a second format completion as first format
        $secondFormat = Format::factory()->create();
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);
        $player = User::factory()->create();

        // Create second format completion
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$player])
            ->create([
                'completion_id' => $completion->id,
                'format_id' => $secondFormat->id,
            ]);

        $payload = [
            ...$this->requestData(),
            'user_ids' => [(string) $player->discord_id],
            'format' => $this->format->id,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}/accept", $payload)
            ->assertStatus(403);
    }

    #[Group('put')]
    public function test_accept_completion_maplist_all_versions_does_not_create_verifications(): void
    {
        // MAPLIST_ALL_VERSIONS is a special format - use the constant
        Format::firstOrCreate(['id' => FormatConstants::MAPLIST_ALL_VERSIONS], [
            'name' => 'Maplist (all versions)',
            'hidden' => true,
            'run_submission_status' => 0,
            'map_submission_status' => 0,
        ]);

        $user = $this->createUserWithPermissions([FormatConstants::MAPLIST_ALL_VERSIONS => ['edit:completion']]);
        $player = User::factory()->create();

        // Create format 2 completion
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$player])
            ->create([
                'completion_id' => $completion->id,
                'format_id' => FormatConstants::MAPLIST_ALL_VERSIONS,
            ]);

        $payload = [
            ...$this->requestData(),
            'user_ids' => [(string) $player->discord_id],
            'format' => FormatConstants::MAPLIST_ALL_VERSIONS,
        ];

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$completion->id}/accept", $payload)
            ->assertStatus(204);

        $this->markTestIncomplete("Rewrite to use GET /maps/{code}/verifications when endpoint exists");

        // Verify NO verifications were created
        $this->assertDatabaseMissing('verifications', [
            'map_code' => $completion->map_code,
            'user_id' => $player->discord_id,
        ]);
    }

    // -- Webhook tests -- //

    #[Group('put')]
    public function test_accept_completion_dispatches_webhook_update_job(): void
    {
        Queue::fake();

        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

        // Update format with webhook URL
        $this->format->update([
            'run_submission_wh' => 'https://discord.com/api/webhooks/test/webhook',
        ]);

        // Set webhook payload on completion
        $this->completion->subm_wh_payload = '12345;{"embeds":[{"color":123456}]}';
        $this->completion->save();

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$this->completion->id}/accept", $this->requestData())
            ->assertStatus(204);

        // Assert job was dispatched
        Queue::assertPushed(UpdateDiscordWebhookJob::class, function ($job) {
            return $job->completionId === $this->completion->id;
        });
    }

    #[Group('put')]
    public function test_accept_completion_without_webhook_payload_does_not_dispatch_job(): void
    {
        Queue::fake();

        $user = $this->createUserWithPermissions([$this->format->id => ['edit:completion']]);

        // No webhook payload set
        $this->assertNull($this->completion->subm_wh_payload);

        $this->actingAs($user, 'discord')
            ->putJson("/api/completions/{$this->completion->id}/accept", $this->requestData())
            ->assertStatus(204);

        // Assert NO job was dispatched
        Queue::assertNotPushed(UpdateDiscordWebhookJob::class);
    }
}
