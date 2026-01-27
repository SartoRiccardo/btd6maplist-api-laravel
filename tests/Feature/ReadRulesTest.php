<?php

namespace Tests\Feature;

use App\Services\Discord\DiscordApiClient;
use Tests\TestCase;

/**
 * Test reading the rules one or more times.
 *
 * @author rikki.sarto@gmail.com
 */
class ReadRulesTest extends TestCase
{

    private const USER_ID = 2000000;
    private const USERNAME = 'test_new_usr';
    private const FAKE_TOKEN = 'fake_discord_token';

    protected function setUp(): void
    {
        parent::setUp();

        // Fake the Discord API for all tests (static method, just like Http::fake())
        DiscordApiClient::fake([
            'id' => (string) self::USER_ID,
            'username' => self::USERNAME,
            'avatar' => '31eb929ef84cce316fa9be34fc9b1c5b',
            'global_name' => 'Test User',
        ]);
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
