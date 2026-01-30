<?php

namespace Tests\Feature\Config;

use App\Models\Config;
use App\Models\ConfigFormat;
use App\Models\User;
use PHPUnit\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\TestsDiscordAuthMiddleware;

/**
 * Test PUT /config endpoint.
 */
class ConfigPutTest extends TestCase
{
    use TestsDiscordAuthMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        ConfigFormat::query()->delete();
        Config::query()->delete();
    }

    protected function endpoint(): string
    {
        return '/api/config';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    protected function requestData(): array
    {
        return [
            'config' => [
                'points_top_map' => 150,
                'points_bottom_map' => 10,
            ],
        ];
    }

    /**
     * Test updating config variables with valid permissions.
     */
    #[Group('put')]
    public function test_update_config_with_permissions(): void
    {
        $cfg1 = Config::factory()->type('float')->forFormats([1, 2])->create(['value' => '100.0']);
        $cfg2 = Config::factory()->type('float')->forFormats([1, 2])->create(['value' => '5.0']);

        $user = $this->createUserWithPermissions([1 => ['edit:config']]);

        $payload = [
            'config' => [
                $cfg1->name => 150,
                $cfg2->name => 10,
            ],
        ];

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/config', $payload)
            ->assertStatus(200)
            ->json();

        $expected = [
            'errors' => [],
            'data' => [
                $cfg1->name => 150,
                $cfg2->name => 10,
            ],
        ];

        $this->assertEquals($expected, $actual);

        // Verify changes persisted
        $actual = $this->getJson('/api/config')
            ->assertStatus(200)
            ->json();

        $this->assertEquals(150, $actual[$cfg1->name]['value']);
        $this->assertEquals(10, $actual[$cfg2->name]['value']);
    }

    /**
     * Test updating config without edit:config permission returns 403.
     */
    #[Group('put')]
    public function test_update_config_without_permission_returns_403(): void
    {
        $cfg = Config::factory()->type('float')->forFormats([1])->create(['value' => '100.0']);
        $user = User::factory()->create();

        $payload = [
            'config' => [
                $cfg->name => 150,
            ],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/config', $payload)
            ->assertStatus(403);
    }

    /**
     * Test updating config with invalid keys returns 422.
     */
    #[Group('put')]
    public function test_update_config_with_invalid_keys_returns_422(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:config']]);

        $payload = [
            'config' => [
                'nonexistent_key' => 100,
            ],
        ];

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/config', $payload)
            ->assertStatus(422)
            ->json();

        $this->assertArrayHasKey('errors', $actual);
        $this->assertArrayHasKey('config.nonexistent_key', $actual['errors']);
    }

    /**
     * Test updating config with type mismatch returns 422.
     */
    #[Group('put')]
    public function test_update_config_with_type_mismatch_returns_422(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:config']]);

        // Test int type mismatch
        $intCfg = Config::factory()->type('int')->forFormats([1])->create(['value' => '50']);
        $payload = [
            'config' => [
                $intCfg->name => 'not_an_int',
            ],
        ];

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/config', $payload)
            ->assertStatus(422)
            ->json();
        $this->assertArrayHasKey('errors', $actual);
        $this->assertArrayHasKey("config.{$intCfg->name}", $actual['errors']);

        // Test float type mismatch
        $floatCfg = Config::factory()->type('float')->forFormats([1])->create(['value' => '100.0']);
        $payload = [
            'config' => [
                $floatCfg->name => 'not_a_float',
            ],
        ];

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/config', $payload)
            ->assertStatus(422)
            ->json();
        $this->assertArrayHasKey('errors', $actual);
        $this->assertArrayHasKey("config.{$floatCfg->name}", $actual['errors']);
    }

    /**
     * Test updating config with valid int type passes.
     */
    #[Group('put')]
    public function test_update_config_with_valid_int_type(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:config']]);

        $cfg = Config::factory()->type('int')->forFormats([1])->create(['value' => '50']);

        $payload = [
            'config' => [
                $cfg->name => 100,
            ],
        ];

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/config', $payload)
            ->assertStatus(200)
            ->json();

        $expected = [
            'errors' => [],
            'data' => [
                $cfg->name => 100,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test updating config with valid float type passes.
     */
    #[Group('put')]
    public function test_update_config_with_valid_float_type(): void
    {
        $cfg = Config::factory()->type('float')->forFormats([1])->create(['value' => '100.0']);

        $user = $this->createUserWithPermissions([1 => ['edit:config']]);

        $payload = [
            'config' => [
                $cfg->name => 150.5,
            ],
        ];

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/config', $payload)
            ->assertStatus(200)
            ->json();

        $expected = [
            'errors' => [],
            'data' => [
                $cfg->name => 150.5,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test updating config without permission for format skips update silently.
     */
    #[Group('put')]
    public function test_update_config_without_format_permission_skips_silently(): void
    {
        $cfg1 = Config::factory()->type('int')->forFormats([1, 2, 51])->create(['value' => '441']);
        $cfg2 = Config::factory()->type('float')->forFormats([1, 2])->create(['value' => '100.0']);

        // User has permission for format 51, not format 1 or 2
        $user = $this->createUserWithPermissions([51 => ['edit:config']]);

        $payload = [
            'config' => [
                $cfg1->name => 450, // Has format 51, should update
                $cfg2->name => 150, // Only has formats 1,2, should skip
            ],
        ];

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/config', $payload)
            ->assertStatus(200)
            ->json();

        $expected = [
            'errors' => [
                $cfg2->name => 'Invalid key',
            ],
            'data' => [
                $cfg1->name => 450,
            ],
        ];

        $this->assertEquals($expected, $actual);

        // Verify cfg2 wasn't changed
        $getConfig = $this->getJson('/api/config')
            ->assertStatus(200)
            ->json();

        $this->assertEquals(100.0, $getConfig[$cfg2->name]['value']);
        $this->assertEquals(450, $getConfig[$cfg1->name]['value']);
    }

    /**
     * Test updating config with missing config field returns 422.
     */
    #[Group('put')]
    public function test_update_config_missing_config_field_returns_422(): void
    {
        $user = $this->createUserWithPermissions([1 => ['edit:config']]);

        $payload = [];

        $actual = $this->actingAs($user, 'discord')
            ->putJson('/api/config', $payload)
            ->assertStatus(422)
            ->json();

        $this->assertArrayHasKey('errors', $actual);
        $this->assertArrayHasKey('config', $actual['errors']);
    }
}
