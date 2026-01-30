<?php

namespace Tests\Traits;

use App\Models\User;
use App\Services\Discord\DiscordApiClient;

/**
 * Trait TestsDiscordAuthMiddleware
 *
 * Injects authentication middleware tests into a test class.
 * The class must define endpoint() and method() abstract methods.
 */
trait TestsDiscordAuthMiddleware
{
    protected const USER_ID = '2000000';
    protected const USERNAME = 'test_new_usr';
    protected const FAKE_TOKEN = 'fake_discord_token';
    protected const INVALID_TOKEN = 'invalid_discord_token';

    /**
     * The endpoint to call for authentication tests
     */
    abstract protected function endpoint(): string;

    /**
     * The HTTP method to use for the endpoint
     */
    abstract protected function method(): string;

    /**
     * Additional data to send with the request (optional)
     */
    protected function requestData(): array
    {
        return [];
    }

    /**
     * Expected status code for successful authenticated request
     */
    protected function expectedSuccessStatusCode(): int
    {
        return 200;
    }

    // -- Helper methods -- //

    /**
     * Make a request with the specified token
     */
    private function makeRequest(?string $token = null)
    {
        $request = $token
            ? $this->withToken($token)
            : $this;

        return match (strtoupper($this->method())) {
            'GET' => $request->getJson($this->endpoint()),
            'POST' => $request->postJson($this->endpoint(), $this->requestData()),
            'PUT' => $request->putJson($this->endpoint(), $this->requestData()),
            'DELETE' => $request->deleteJson($this->endpoint(), $this->requestData()),
        };
    }

    /**
     * Set up Discord API fakes with default test profile
     */
    private function setupDiscordFakes(): void
    {
        DiscordApiClient::fake([
            'id' => self::USER_ID,
            'username' => self::USERNAME,
            'discriminator' => '0000',
            'avatar' => null,
        ]);
    }

    // -- Injected tests -- //

    /**
     * Test calling the endpoint with no Authorization header returns 401.
     */
    public function test_no_bearer_token_returns_401(): void
    {
        $this->setupDiscordFakes();

        $this->makeRequest()
            ->assertStatus(401)
            ->assertJson(['error' => 'No token found or invalid token']);
    }

    /**
     * Test calling the endpoint with malformed Bearer token returns 401.
     */
    public function test_malformed_bearer_token_returns_401(): void
    {
        $this->setupDiscordFakes();

        $this->withToken('')->withHeader('Authorization', 'Bearer')
            ->{strtolower($this->method()) . 'Json'}(
                $this->endpoint(),
                $this->requestData()
            )
                ->assertStatus(401)
                ->assertJson(['error' => 'No token found or invalid token']);
    }

    /**
     * Test calling the endpoint with an invalid Discord token returns 401.
     */
    public function test_invalid_discord_token_returns_401(): void
    {
        DiscordApiClient::fakeFailure();

        $this->makeRequest(self::INVALID_TOKEN)
            ->assertStatus(401)
            ->assertJson(['error' => 'No token found or invalid token']);
    }

    /**
     * Test calling the endpoint with a valid token creates a user (idempotent).
     * Verifies that:
     * - First request creates the user in the database
     * - Second request does not create a duplicate user
     */
    public function test_valid_token_creates_user(): void
    {
        $this->setupDiscordFakes();

        $this->makeRequest(self::FAKE_TOKEN);

        $this->assertDatabaseHas('users', [
            'discord_id' => self::USER_ID,
            'name' => self::USERNAME,
        ]);

        $userCountBefore = User::count();

        $this->makeRequest(self::FAKE_TOKEN);

        $this->assertEquals($userCountBefore, User::count());
    }
}

