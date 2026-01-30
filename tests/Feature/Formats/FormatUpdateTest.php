<?php

namespace Tests\Feature\Formats;

use App\Models\Format;
use App\Models\User;
use PHPUnit\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

/**
 * Test PUT /formats/{id} endpoint.
 */
class FormatUpdateTest extends TestCase
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
        return 'PUT';
    }

    protected function requestData(): array
    {
        return [
            'hidden' => true,
            'run_submission_status' => 'closed',
            'map_submission_status' => 'closed',
            'map_submission_wh' => null,
            'run_submission_wh' => null,
            'emoji' => null,
        ];
    }

    // -- Feature tests -- //

    /**
     * Test updating a format successfully with valid data.
     */
    #[Group('put')]
    public function test_update_format_success(): void
    {
        $format = Format::factory()->create();
        $user = $this->createUserWithPermissions([$format->id => ['edit:config']]);

        $payload = [
            ...$this->requestData(),
            'hidden' => true,
            'run_submission_status' => 'lcc_only',
            'map_submission_status' => 'open_chimps',
            'map_submission_wh' => fake()->url(),
            'run_submission_wh' => fake()->url(),
            'emoji' => fake()->emoji(),
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/formats/' . $format->id, $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/formats/' . $format->id)
            ->assertStatus(200)
            ->json();

        $expected = Format::jsonStructure([
            ...$format->toArray(),
            ...$payload,
        ], strict: false);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test updating a format with null webhook URLs.
     */
    #[Group('put')]
    public function test_update_format_with_null_webhooks(): void
    {
        $format = Format::factory()->create();
        $user = $this->createUserWithPermissions([$format->id => ['edit:config']]);

        $payload = [
            ...$this->requestData(),
            'map_submission_wh' => null,
            'run_submission_wh' => null,
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/formats/' . $format->id, $payload)
            ->assertStatus(204);

        $actual = $this->actingAs($user, 'discord')
            ->getJson('/api/formats/' . $format->id)
            ->assertStatus(200)
            ->json();

        $expected = Format::jsonStructure([
            ...$format->toArray(),
            ...$payload,
        ], strict: false);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test updating a format without edit:config permission returns 403.
     */
    #[Group('put')]
    public function test_update_format_without_permission_returns_403(): void
    {
        $user = User::factory()->create();
        $payload = $this->requestData();

        $this->actingAs($user, 'discord')
            ->putJson('/api/formats/' . $this->format->id, $payload)
            ->assertStatus(403)
            ->assertJson(['error' => 'Missing edit:config permission for this format']);
    }

    /**
     * Test updating a format with invalid fields returns 422.
     */
    #[Group('put')]
    public function test_update_format_validation_errors_returns_422(): void
    {
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:config']]);

        $payload = [
            ...$this->requestData(),
            'map_submission_status' => 'invalid_status',
            'run_submission_status' => 'invalid_status',
            'map_submission_wh' => 'not-a-valid-url',
        ];

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/formats/' . $this->format->id, $payload)
            ->assertStatus(422)
            ->json();

        $this->assertArrayHasKey('errors', $actual);
        $this->assertArrayHasKey('map_submission_status', $actual['errors']);
        $this->assertArrayHasKey('run_submission_status', $actual['errors']);
        $this->assertArrayHasKey('map_submission_wh', $actual['errors']);
    }

    /**
     * Test updating a format with missing required fields returns 422.
     */
    #[Group('put')]
    public function test_update_format_missing_required_fields_returns_422(): void
    {
        $user = $this->createUserWithPermissions([$this->format->id => ['edit:config']]);
        $payload = [];

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/formats/' . $this->format->id, $payload)
            ->assertStatus(422)
            ->json();

        $this->assertArrayHasKey('errors', $actual);
        $this->assertArrayHasKey('hidden', $actual['errors']);
        $this->assertArrayHasKey('run_submission_status', $actual['errors']);
        $this->assertArrayHasKey('map_submission_status', $actual['errors']);
    }

    /**
     * Test updating a nonexistent format returns 404.
     */
    #[Group('put')]
    public function test_update_format_nonexistent_returns_404(): void
    {
        $this->actingAs($this->createUserWithPermissions([$this->format->id => ['edit:config']]), 'discord')
            ->putJson('/api/formats/' . random_int(1_000_000, 10_000_000), $this->requestData())
            ->assertStatus(404);
    }
}
