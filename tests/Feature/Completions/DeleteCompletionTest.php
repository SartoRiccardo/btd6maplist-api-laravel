<?php

namespace Tests\Feature\Completions;

use App\Jobs\UpdateDiscordWebhookJob;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Format;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Attributes\Group;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;

class DeleteCompletionTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    private User $player;
    private User $userWithPermission;
    private Completion $completion;
    private CompletionMeta $meta;
    private int $formatId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = User::factory()->create();
        $this->completion = Completion::factory()->create();
        $this->meta = CompletionMeta::factory()
            ->withPlayers([$this->player])
            ->create(['completion_id' => $this->completion->id]);
        $this->formatId = $this->meta->format_id;
        $this->userWithPermission = $this->createUserWithPermissions([$this->formatId => ['delete:completion']]);
    }

    protected function endpoint(): string
    {
        return '/api/completions/' . $this->completion->id;
    }

    protected function method(): string
    {
        return 'DELETE';
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 204;
    }

    #[Group('delete')]
    public function test_delete_completion_success(): void
    {
        $this->actingAs($this->userWithPermission, 'discord')
            ->deleteJson('/api/completions/' . $this->completion->id)
            ->assertStatus(204);

        // Verify it's soft deleted (still accessible but with deleted_on set)
        $actual = $this->getJson('/api/completions/' . $this->completion->id)
            ->assertStatus(200)
            ->json();

        $this->assertNotNull($actual['deleted_on']);
    }

    #[Group('delete')]
    public function test_delete_completion_twice_returns_no_content(): void
    {
        $this->actingAs($this->userWithPermission, 'discord')
            ->deleteJson('/api/completions/' . $this->completion->id)
            ->assertStatus(204);

        // Second delete should also return 204 (idempotent)
        $this->actingAs($this->userWithPermission, 'discord')
            ->deleteJson('/api/completions/' . $this->completion->id)
            ->assertStatus(204);
    }

    #[Group('delete')]
    public function test_delete_completion_without_permission_returns_forbidden(): void
    {
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->deleteJson('/api/completions/' . $this->completion->id)
            ->assertStatus(403);
    }

    #[Group('delete')]
    public function test_delete_completion_with_scoped_permissions(): void
    {
        // Create a completion with a different format than what user has permissions for
        $format = Format::factory()->create();
        $user = $this->createUserWithPermissions([$format->id => ['delete:completion']]);

        $this->actingAs($user, 'discord')
            ->deleteJson('/api/completions/' . $this->completion->id)
            ->assertStatus(403);
    }

    #[Group('delete')]
    public function test_delete_completion_returns_404_for_nonexistent_completion(): void
    {
        $this->actingAs($this->userWithPermission, 'discord')
            ->deleteJson('/api/completions/999999')
            ->assertStatus(404);
    }

    #[Group('delete')]
    public function test_deleted_completion_still_accessible_via_get(): void
    {
        // Get original completion data
        $original = $this->getJson('/api/completions/' . $this->completion->id)
            ->assertStatus(200)
            ->json();
        unset($original['deleted_on']);

        // Delete it
        $this->actingAs($this->userWithPermission, 'discord')
            ->deleteJson('/api/completions/' . $this->completion->id)
            ->assertStatus(204);

        // Verify it's still accessible
        $deleted = $this->getJson('/api/completions/' . $this->completion->id)
            ->assertStatus(200)
            ->json();
        unset($deleted['deleted_on']);

        $this->assertEquals($original, $deleted);
    }

    #[Group('delete')]
    public function test_get_before_and_after_deletion_are_identical(): void
    {
        // GET before deletion
        $before = $this->getJson('/api/completions/' . $this->completion->id)
            ->assertStatus(200)
            ->json();

        // DELETE
        $this->actingAs($this->userWithPermission, 'discord')
            ->deleteJson('/api/completions/' . $this->completion->id)
            ->assertStatus(204);

        // GET after deletion
        $after = $this->getJson('/api/completions/' . $this->completion->id)
            ->assertStatus(200)
            ->json();

        unset($before['deleted_on'], $after['deleted_on']);
        $this->assertEquals($before, $after);
    }

    #[Group('delete')]
    public function test_delete_completion_dispatches_webhook_update_job_for_unaccepted(): void
    {
        Queue::fake();

        // Create an unaccepted completion with webhook payload
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$this->player])
            ->create([
                'completion_id' => $completion->id,
                'format_id' => $this->formatId,
            ]);

        // Set webhook payload
        $completion->subm_wh_payload = '12345;{"embeds":[{"color":123456}]}';
        $completion->save();

        $user = $this->createUserWithPermissions([$this->formatId => ['delete:completion']]);

        $this->actingAs($user, 'discord')
            ->deleteJson('/api/completions/' . $completion->id)
            ->assertStatus(204);

        // Assert job was dispatched with fail=true
        Queue::assertPushed(UpdateDiscordWebhookJob::class, function ($job) use ($completion) {
            return $job->completionId === $completion->id && $job->fail === true;
        });
    }

    #[Group('delete')]
    public function test_delete_completion_does_not_dispatch_webhook_job_for_accepted(): void
    {
        Queue::fake();

        // Create an accepted completion with webhook payload
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$this->player])
            ->accepted()
            ->create([
                'completion_id' => $completion->id,
                'format_id' => $this->formatId,
            ]);

        // Set webhook payload (even though accepted, this shouldn't happen but let's test)
        $completion->subm_wh_payload = '12345;{"embeds":[{"color":123456}]}';
        $completion->save();

        $user = $this->createUserWithPermissions([$this->formatId => ['delete:completion']]);

        $this->actingAs($user, 'discord')
            ->deleteJson('/api/completions/' . $completion->id)
            ->assertStatus(204);

        // Assert NO job was dispatched (because it was accepted)
        Queue::assertNotPushed(UpdateDiscordWebhookJob::class);
    }

    #[Group('delete')]
    public function test_delete_completion_does_not_dispatch_webhook_job_without_payload(): void
    {
        Queue::fake();

        $user = $this->createUserWithPermissions([$this->formatId => ['delete:completion']]);

        $this->actingAs($user, 'discord')
            ->deleteJson('/api/completions/' . $this->completion->id)
            ->assertStatus(204);

        // Assert NO job was dispatched (no webhook payload)
        Queue::assertNotPushed(UpdateDiscordWebhookJob::class);
    }
}
