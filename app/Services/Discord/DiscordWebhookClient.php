<?php

namespace App\Services\Discord;

use Illuminate\Support\Facades\Http;

class DiscordWebhookClient
{
    private const PENDING_COLOR = 0x1e88e5;
    private const FAIL_COLOR = 0xb71c1c;
    private const ACCEPT_COLOR = 0x43a047;

    protected static ?bool $fakeResult = null;

    /**
     * Update a Discord webhook message for a completion acceptance.
     *
     * @param string $webhookUrl The webhook URL
     * @param string $messageId The message ID to update
     * @param array $payload The original payload (with embeds)
     * @param bool $fail Whether to show failed (red) or accepted (green) status
     * @return bool True if successful, false otherwise
     */
    public function updateWebhookMessage(string $webhookUrl, string $messageId, array $payload, bool $fail = false): bool
    {
        if (self::$fakeResult !== null) {
            return self::$fakeResult;
        }

        // Update the embed color
        if (isset($payload['embeds'][0])) {
            $payload['embeds'][0]['color'] = $fail ? self::FAIL_COLOR : self::ACCEPT_COLOR;
        }

        $response = Http::patch("{$webhookUrl}/messages/{$messageId}", $payload);

        return $response->successful();
    }

    /**
     * Fake the webhook client for testing.
     *
     * @param bool $result The result to return from updateWebhookMessage
     */
    public static function fake(bool $result = true): void
    {
        self::$fakeResult = $result;
    }

    /**
     * Clear the fake result.
     */
    public static function clearFake(): void
    {
        self::$fakeResult = null;
    }
}
