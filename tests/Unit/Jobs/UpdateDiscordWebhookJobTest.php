<?php

namespace Tests\Unit\Jobs;

use App\Jobs\UpdateDiscordWebhookJob;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\Format;
use App\Models\User;
use App\Services\Discord\DiscordWebhookClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateDiscordWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    private User $player;
    private Format $format;

    protected function setUp(): void
    {
        parent::setUp();
        $this->player = User::factory()->create();
        $this->format = Format::factory()->create([
            'run_submission_wh' => 'https://discord.com/api/webhooks/test/webhook',
        ]);
    }

    protected function tearDown(): void
    {
        DiscordWebhookClient::clearFake();
        parent::tearDown();
    }

    public function test_job_updates_webhook_and_clears_payload(): void
    {
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$this->player])
            ->create([
                'completion_id' => $completion->id,
                'format_id' => $this->format->id,
            ]);

        // Set webhook payload directly in DB to avoid model casting
        $originalPayload = '12345;{"embeds":[{"color":123456}]}';
        Completion::where('id', $completion->id)->update([
            'subm_wh_payload' => $originalPayload,
        ]);

        // Fake the webhook client to return success
        DiscordWebhookClient::fake(true);

        // Run the job
        $job = new UpdateDiscordWebhookJob($completion->id);
        $job->handle(app(DiscordWebhookClient::class));

        // Assert payload was cleared
        $this->assertNull($completion->fresh()->subm_wh_payload);
    }

    public function test_job_handles_invalid_payload_format(): void
    {
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$this->player])
            ->create([
                'completion_id' => $completion->id,
                'format_id' => $this->format->id,
            ]);

        // Set invalid webhook payload (no semicolon separator)
        Completion::where('id', $completion->id)->update([
            'subm_wh_payload' => 'invalid_payload_without_semicolon',
        ]);

        DiscordWebhookClient::fake(true);

        // Run the job - should not throw exception
        $job = new UpdateDiscordWebhookJob($completion->id);
        $job->handle(app(DiscordWebhookClient::class));

        // Payload should not be cleared since webhook wasn't updated
        $this->assertNotNull($completion->fresh()->subm_wh_payload);
    }

    public function test_job_handles_discord_error_gracefully(): void
    {
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$this->player])
            ->create([
                'completion_id' => $completion->id,
                'format_id' => $this->format->id,
            ]);

        $originalPayload = '12345;{"embeds":[{"color":123456}]}';
        Completion::where('id', $completion->id)->update([
            'subm_wh_payload' => $originalPayload,
        ]);

        // Fake the webhook client to return failure
        DiscordWebhookClient::fake(false);

        // Run the job
        $job = new UpdateDiscordWebhookJob($completion->id);
        $job->handle(app(DiscordWebhookClient::class));

        // Payload should NOT be cleared when webhook update fails
        $this->assertEquals($originalPayload, $completion->fresh()->subm_wh_payload);
    }

    public function test_job_handles_completion_without_webhook_payload(): void
    {
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$this->player])
            ->create([
                'completion_id' => $completion->id,
                'format_id' => $this->format->id,
            ]);

        // No webhook payload set (null)
        $this->assertNull($completion->subm_wh_payload);

        DiscordWebhookClient::fake(true);

        // Run the job - should return early without error
        $job = new UpdateDiscordWebhookJob($completion->id);
        $job->handle(app(DiscordWebhookClient::class));

        // Still no payload
        $this->assertNull($completion->fresh()->subm_wh_payload);
    }

    public function test_job_handles_nonexistent_completion(): void
    {
        DiscordWebhookClient::fake(true);

        // Run the job with non-existent completion ID
        $job = new UpdateDiscordWebhookJob(999999);
        $job->handle(app(DiscordWebhookClient::class));

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function test_job_handles_json_payload_without_separator(): void
    {
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$this->player])
            ->create([
                'completion_id' => $completion->id,
                'format_id' => $this->format->id,
            ]);

        // Payload without semicolon separator - can't extract message ID
        $invalidPayload = '{"invalid":"array"}';
        Completion::where('id', $completion->id)->update([
            'subm_wh_payload' => $invalidPayload,
        ]);

        DiscordWebhookClient::fake(true);

        // Run the job - should not throw exception
        $job = new UpdateDiscordWebhookJob($completion->id);
        $job->handle(app(DiscordWebhookClient::class));

        // Payload should NOT be cleared (webhook update couldn't happen)
        $this->assertEquals($invalidPayload, $completion->fresh()->subm_wh_payload);
    }

    public function test_job_updates_webhook_with_fail_color(): void
    {
        $completion = Completion::factory()->create();
        CompletionMeta::factory()
            ->withPlayers([$this->player])
            ->create([
                'completion_id' => $completion->id,
                'format_id' => $this->format->id,
            ]);

        // Set webhook payload directly in DB to avoid model casting
        $originalPayload = '12345;{"embeds":[{"color":123456}]}';
        Completion::where('id', $completion->id)->update([
            'subm_wh_payload' => $originalPayload,
        ]);

        DiscordWebhookClient::fake(true);

        // Run the job with fail=true
        $job = new UpdateDiscordWebhookJob($completion->id, fail: true);
        $job->handle(app(DiscordWebhookClient::class));

        // Assert payload was cleared
        $this->assertNull($completion->fresh()->subm_wh_payload);
    }
}
