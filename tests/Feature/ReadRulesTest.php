<?php

namespace Tests\Feature;

use App\Services\Discord\DiscordApiClient;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

/**
 * Test reading the rules one or more times.
 */
class ReadRulesTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        DiscordApiClient::fake([
            'id' => self::USER_ID,
            'username' => self::USERNAME,
            'discriminator' => '0000',
            'avatar' => null,
        ]);
    }

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

    /**
     * Test reading the rules one or more times.
     */
    public function test_read_rules(): void
    {
        // 1. First /auth call - verify has_seen_popup is false
        $firstProfile = $this->withToken(self::FAKE_TOKEN)
            ->postJson('/api/auth')
            ->assertStatus(200)
            ->json();

        $expectedFirstProfile = [
            'id' => (string) self::USER_ID,
            'name' => self::USERNAME,
            'oak' => null,
            'has_seen_popup' => false,
            'is_banned' => false,
            'permissions' => [],
            'roles' => [],
            'completions' => [],
        ];
        $this->assertEquals($expectedFirstProfile, $firstProfile);

        // 2. First /read-rules call - verify 204
        $this->withToken(self::FAKE_TOKEN)
            ->putJson('/api/read-rules')
            ->assertStatus(204);

        // 3. Second /auth call - verify has_seen_popup is now true
        $secondProfile = $this->withToken(self::FAKE_TOKEN)
            ->postJson('/api/auth')
            ->assertStatus(200)
            ->json();

        $expectedSecondProfile = [
            'id' => (string) self::USER_ID,
            'name' => self::USERNAME,
            'oak' => null,
            'has_seen_popup' => true,
            'is_banned' => false,
            'permissions' => [],
            'roles' => [],
            'completions' => [],
        ];
        $this->assertEquals($expectedSecondProfile, $secondProfile);

        // 4. Second /read-rules call - verify still 204 (idempotent)
        $this->withToken(self::FAKE_TOKEN)
            ->putJson('/api/read-rules')
            ->assertStatus(204);

        // 5. Third /auth call - verify profile unchanged (idempotent)
        $thirdProfile = $this->withToken(self::FAKE_TOKEN)
            ->postJson('/api/auth')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expectedSecondProfile, $thirdProfile);
    }
}

