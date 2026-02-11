<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Discord\DiscordApiClient;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;

class ReadRulesTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function endpoint(): string
    {
        return '/api/read-rules';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 204;
    }

    #[Group('put')]
    #[Group('auth')]
    #[Group('read-rules')]
    public function test_reading_rules_sets_has_seen_popup_to_true(): void
    {
        $this->setupDiscordFakes();

        // First request creates user with has_seen_popup = false
        $this->withToken(self::FAKE_TOKEN)
            ->putJson('/api/read-rules')
            ->assertStatus(204);

        // Verify user was created with has_seen_popup = true
        $this->assertDatabaseHas('users', [
            'discord_id' => self::USER_ID,
            'has_seen_popup' => true,
        ]);

        // Verify user in DB has has_seen_popup = true
        $user = User::find(self::USER_ID);
        $this->assertTrue($user->has_seen_popup);
    }

    #[Group('put')]
    #[Group('auth')]
    #[Group('read-rules')]
    public function test_reading_rules_twice_is_idempotent(): void
    {
        $this->setupDiscordFakes();

        // First request creates user and sets has_seen_popup = true
        $this->withToken(self::FAKE_TOKEN)
            ->putJson('/api/read-rules')
            ->assertStatus(204);

        $userAfterFirst = User::find(self::USER_ID);
        $this->assertTrue($userAfterFirst->has_seen_popup);

        // Read rules again
        $this->withToken(self::FAKE_TOKEN)
            ->putJson('/api/read-rules')
            ->assertStatus(204);

        // Verify user state hasn't changed
        $userAfterSecond = User::find(self::USER_ID);
        $this->assertEquals($userAfterFirst->has_seen_popup, $userAfterSecond->has_seen_popup);
        $this->assertTrue($userAfterSecond->has_seen_popup);
    }
}
