<?php

namespace Tests\Feature\Formats;

use App\Models\Format;
use App\Models\User;
use PHPUnit\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

/**
 * Test GET /formats/{id} endpoint.
 */
class FormatShowTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected Format $format;

    protected function setUp(): void
    {
        parent::setUp();
        $this->format = Format::factory()->create();
    }

    // -- TestsDiscordAuthMiddleware trait requirements -- //

    protected function endpoint(): string
    {
        return "/api/formats/{$this->format->id}";
    }

    protected function method(): string
    {
        return 'GET';
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 200;
    }

    // -- Feature tests -- //

    /**
     * Test getting a format by ID returns full format including webhooks when user has edit:config permission.
     */
    #[Group('get')]
    public function test_get_format_by_id_returns_full_format_including_webhooks(): void
    {
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:config']]);
        $actual = $this->actingAs($user, 'discord')
            ->getJson($this->endpoint())
            ->assertStatus(200)
            ->json();

        $expected = Format::jsonStructure($this->format->toFullArray(), strict: false);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test getting a nonexistent format returns 404.
     */
    #[Group('get')]
    public function test_get_format_by_id_nonexistent_returns_404(): void
    {
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:config']]);
        $this->actingAs($user, 'discord')
            ->getJson('api/formats/' . random_int(1_000_000, 10_000_000))
            ->assertStatus(404);
    }

    /**
     * Test getting a format without edit:config permission returns 403.
     */
    #[Group('get')]
    public function test_get_format_by_id_without_permission_returns_403(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'discord')
            ->getJson($this->endpoint())
            ->assertStatus(403)
            ->assertJson(['error' => 'Missing edit:config permission for this format']);
    }

    /**
     * Test getting a format with edit:config permission for a different format returns 403.
     */
    #[Group('get')]
    public function test_get_format_by_id_with_wrong_format_permission_returns_403(): void
    {
        $format = Format::factory()->create();
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:config']]);

        $this->actingAS($user, 'discord')
            ->getJson("/api/formats/{$format->id}")
            ->assertStatus(403)
            ->assertJson(['error' => 'Missing edit:config permission for this format']);
    }
}
