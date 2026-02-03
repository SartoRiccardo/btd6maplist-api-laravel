<?php

namespace App\Jobs;

use App\Models\Completion;
use App\Models\Format;
use App\Services\Discord\DiscordWebhookClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateDiscordWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $completionId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $completionId)
    {
        $this->completionId = $completionId;
    }

    /**
     * Execute the job.
     */
    public function handle(DiscordWebhookClient $webhookClient): void
    {
        $completion = Completion::find($this->completionId);

        if (!$completion || $completion->subm_wh_payload === null) {
            return; // Nothing to do
        }

        // Parse payload: "message_id;payload_json"
        $payload = $completion->subm_wh_payload;
        if (!is_string($payload) || str_contains($payload, ';') === false) {
            Log::warning('Invalid webhook payload format', ['completion_id' => $this->completionId]);
            return;
        }

        [$messageId, $payloadJson] = explode(';', $payload, 2);
        $payload = json_decode($payloadJson, true);

        if (!$payload || !isset($payload['embeds'][0])) {
            Log::warning('Invalid webhook payload JSON', ['completion_id' => $this->completionId]);
            return;
        }

        // Get the format to retrieve webhook URL
        $meta = $completion->latestMeta;
        if (!$meta) {
            return;
        }

        $format = Format::find($meta->format_id);
        if (!$format || $format->run_submission_wh === null) {
            return; // No webhook configured
        }

        // Update the webhook
        $success = $webhookClient->updateWebhookMessage(
            $format->run_submission_wh,
            $messageId,
            $payload,
            fail: false
        );

        if ($success) {
            // Clear the payload if update succeeded
            $completion->subm_wh_payload = null;
            $completion->save();
        }
    }
}
