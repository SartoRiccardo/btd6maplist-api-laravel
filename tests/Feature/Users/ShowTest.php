<?php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Services\NinjaKiwi\NinjaKiwiApiClient;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;

class ShowTest extends TestCase
{
    #[Group('get')]
    #[Group('users')]
    public function test_user_can_be_retrieved(): void
    {
        $user = User::factory()->create();

        $actual = $this->getJson("/api/users/{$user->discord_id}")
            ->assertStatus(200)
            ->json();

        $expected = User::jsonStructure($user->toArray());

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('users')]
    public function test_returns_404_when_user_not_found(): void
    {
        $actual = $this->getJson('/api/users/999999999')
            ->assertStatus(404)
            ->json();

        $this->assertEquals([
            'message' => 'Not Found',
        ], $actual);
    }

    #[Group('get')]
    #[Group('users')]
    public function test_include_flair_with_no_oak_returns_null_urls(): void
    {
        $user = User::factory()->create(['nk_oak' => null]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=flair")
            ->assertStatus(200)
            ->json();

        $expected = User::jsonStructure([
            ...$user->toArray(),
            'avatar_url' => null,
            'banner_url' => null,
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('users')]
    public function test_include_flair_with_oak_returns_actual_urls(): void
    {
        $user = User::factory()->create(['nk_oak' => 'test_oak_123']);

        NinjaKiwiApiClient::fake([
            'avatar_url' => 'https://example.com/avatar.png',
            'banner_url' => 'https://example.com/banner.png',
        ]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=flair")
            ->assertStatus(200)
            ->json();

        $expected = User::jsonStructure([
            ...$user->toArray(),
            'avatar_url' => 'https://example.com/avatar.png',
            'banner_url' => 'https://example.com/banner.png',
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('users')]
    public function test_include_random_value_does_not_add_flair_fields(): void
    {
        $user = User::factory()->create(['nk_oak' => 'test_oak_123']);

        NinjaKiwiApiClient::fake([
            'avatar_url' => 'https://example.com/avatar.png',
            'banner_url' => 'https://example.com/banner.png',
        ]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=random_stuff")
            ->assertStatus(200)
            ->json();

        $expected = User::jsonStructure($user->toArray());

        $this->assertEquals($expected, $actual);
    }

    #[Group("get")]
    #[Group("users")]
    public function test_include_flair_with_oak_when_nk_api_returns_error(): void
    {
        $user = User::factory()->create(["nk_oak" => "test_oak_123"]);

        // Fake NK API to return an error response (404 user not found)
        Http::fake([
            "https://data.ninjakiwi.com/btd6/users/test_oak_123*" => Http::response(null, 400),
        ]);

        $actual = $this->getJson("/api/users/{$user->discord_id}?include=flair")
            ->assertStatus(200)
            ->json();

        // When NK API fails, avatar_url and banner_url should be null
        $this->assertNull($actual["avatar_url"]);
        $this->assertNull($actual["banner_url"]);
    }
}
