<?php

namespace Tests\Feature\Roles;

use App\Models\AchievementRole;
use App\Models\DiscordRole;
use App\Models\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Traits\TestsDiscordAuthMiddleware;
use Tests\TestCase;

class AchievementRoleUpdateTest extends TestCase
{
    use RefreshDatabase, TestsDiscordAuthMiddleware;

    private Format $format1;
    private Format $format2;
    private Format $format3;


    protected function setUp(): void
    {
        parent::setUp();
        // Create formats that tests will use
        $this->format1 = Format::factory()->create(['id' => 1, 'name' => 'Maplist']);
        $this->format2 = Format::factory()->create(['id' => 2, 'name' => 'Other Format']);
        $this->format3 = Format::factory()->create(['id' => 51, 'name' => 'Expert List']);
    }

    protected function endpoint(): string
    {
        return '/api/roles/achievement';
    }

    protected function method(): string
    {
        return 'PUT';
    }

    protected function requestData(): array
    {
        return [
            'lb_format' => $this->format1->id,
            'lb_type' => 'points',
            'roles' => []
        ];
    }

    protected function expectedSuccessStatusCode(): int
    {
        return 204;
    }

    protected function makeLinkedRole(): array
    {
        return [
            'guild_id' => fake()->randomNumber(5, true) . str_repeat('0', 12),
            'role_id' => fake()->randomNumber(5, true) . str_repeat('0', 12)
        ];
    }

    /**
     * Helper method to create a sample role data array.
     */
    protected function makeSampleRole(int $threshold, array $overrides = []): array
    {
        return array_merge([
            'threshold' => $threshold,
            'for_first' => false,
            'tooltip_description' => null,
            'name' => 'Test Role',
            'clr_border' => 0xFF0000,
            'clr_inner' => 0x00FF00,
            'linked_roles' => [
                [
                    'guild_id' => fake()->randomNumber(5, true) . str_repeat('0', 12),
                    'role_id' => fake()->randomNumber(5, true) . str_repeat('0', 12)
                ]
            ]
        ], $overrides);
    }

    #[Group('put')]
    public function test_forbidden_without_permission()
    {
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [
                ...$this->requestData(),
                'roles' => [$this->makeSampleRole(100)]
            ])
            ->assertStatus(403)
            ->assertJson(['error' => "You are missing edit:achievement_roles on {$this->format1->id}"]);
    }

    #[Group('put')]
    public function test_forbidden_wrong_format_permission()
    {
        $user = $this->createUserWithPermissions([$this->format1->id => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [
                ...$this->requestData(),
                'lb_format' => $this->format2->id,
                'roles' => [$this->makeSampleRole(100)]
            ])
            ->assertStatus(403)
            ->assertJson(['error' => "You are missing edit:achievement_roles on {$this->format2->id}"]);
    }

    #[Group('put')]
    public function test_validation_fails_when_required_fields_missing()
    {
        $user = $this->createUserWithPermissions([$this->format1->id => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lb_format', 'lb_type', 'roles']]);
    }

    #[Group('put')]
    public function test_validation_fails_for_invalid_fields()
    {
        $user = $this->createUserWithPermissions([$this->format1->id => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [
                ...$this->requestData(),
                'lb_format' => 99999,
                'lb_type' => 'invalid_type',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lb_format', 'lb_type']]);
    }

    #[Group('put')]
    public function test_multiple_for_first_roles()
    {
        $user = $this->createUserWithPermissions([$this->format1->id => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [
                ...$this->requestData(),
                'roles' => [
                    $this->makeSampleRole(0, ['for_first' => true]),
                    $this->makeSampleRole(10, ['for_first' => true]),
                ],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    #[Group('put')]
    public function test_negative_threshold_non_first()
    {
        $user = $this->createUserWithPermissions([$this->format1->id => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [
                ...$this->requestData(),
                'roles' => [$this->makeSampleRole(-1, ['for_first' => false])]
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['roles.0.threshold']]);
    }

    #[Group('put')]
    public function test_empty_name()
    {
        $user = $this->createUserWithPermissions([$this->format1->id => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [
                ...$this->requestData(),
                'roles' => [$this->makeSampleRole(100, ['name' => ''])]
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['roles.0.name']]);
    }

    #[Group('put')]
    public function test_validation_fails_when_fields_too_long()
    {
        $user = $this->createUserWithPermissions([$this->format1->id => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [
                ...$this->requestData(),
                'roles' => [
                    $this->makeSampleRole(100, [
                        'name' => str_repeat('a', 33),
                        'tooltip_description' => str_repeat('a', 129)
                    ])
                ]
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['roles.0.name', 'roles.0.tooltip_description']]);
    }

    #[Group('put')]
    public function test_validation_fails_for_invalid_border_color()
    {
        $user = $this->createUserWithPermissions([$this->format1->id => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [
                ...$this->requestData(),
                'roles' => [
                    $this->makeSampleRole(100, ['clr_border' => -1]),
                    $this->makeSampleRole(200, ['clr_border' => 16777216]),
                    $this->makeSampleRole(300, ['clr_inner' => -1]),
                    $this->makeSampleRole(400, ['clr_inner' => 16777216]),
                ]
            ])
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'roles.0.clr_border',
                    'roles.1.clr_border',
                    'roles.2.clr_inner',
                    'roles.3.clr_inner',
                ]
            ]);
    }

    #[Group('put')]
    public function test_duplicate_thresholds()
    {
        $user = $this->createUserWithPermissions([$this->format1->id => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [
                'lb_format' => $this->format1->id,
                'lb_type' => 'points',
                'roles' => [
                    $this->makeSampleRole(100),
                    $this->makeSampleRole(100)
                ]
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['roles.0.threshold', 'roles.1.threshold']]);
    }

    #[Group('put')]
    public function test_duplicate_discord_roles_in_request()
    {
        $user = $this->createUserWithPermissions([$this->format1->id => ['edit:achievement_roles']]);

        $linkedRole = [
            'guild_id' => '123456789012345678',
            'role_id' => '987654321098765432',
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [
                'lb_format' => $this->format1->id,
                'lb_type' => 'points',
                'roles' => [$this->makeSampleRole(100, ['linked_roles' => [$linkedRole, $linkedRole]])]
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    #[Group('put')]
    public function test_discord_role_used_elsewhere()
    {
        $user = $this->createUserWithPermissions([
            $this->format1->id => ['edit:achievement_roles'],
            $this->format2->id => ['edit:achievement_roles']
        ]);

        $existingRole = AchievementRole::factory()->create(['lb_format' => $this->format2->id]);
        $dr = DiscordRole::factory()->forAchievement($existingRole)->create();

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [
                ...$this->requestData(),
                'roles' => [
                    $this->makeSampleRole(100, [
                        'linked_roles' => [['role_id' => $dr->id]]
                    ])
                ]
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    #[Group('put')]
    public function test_validation_fails_for_non_numeric_discord_ids()
    {
        $user = $this->createUserWithPermissions([$this->format1->id => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [
                'lb_format' => $this->format1->id,
                'lb_type' => 'points',
                'roles' => [
                    $this->makeSampleRole(100, ['linked_roles' => [['guild_id' => 'not_a_number']]]),
                    $this->makeSampleRole(200, ['linked_roles' => [['role_id' => 'not_a_number']]]),
                ]
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['roles.0.linked_roles.0.guild_id', 'roles.1.linked_roles.0.role_id']]);
    }

    #[Group('put')]
    public function test_update_achievement_roles_success()
    {
        $user = $this->createUserWithPermissions([$this->format1->id => ['edit:achievement_roles']]);

        $linkedRole1 = $this->makeLinkedRole();
        $linkedRole2 = $this->makeLinkedRole();

        $role1 = $this->makeSampleRole(100, ['linked_roles' => [$linkedRole1]]);
        $role2 = $this->makeSampleRole(200, ['linked_roles' => [$linkedRole2]]);

        $payload = [
            ...$this->requestData(),
            'lb_format' => $this->format1->id,
            'roles' => [$role1, $role2],
        ];

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', $payload)
            ->assertStatus(204);

        $actual = $this->getJson('/api/roles/achievement')
            ->assertStatus(200)
            ->json();

        $expected = [
            AchievementRole::jsonStructure([
                'lb_format' => $this->format1->id,
                'lb_type' => 'points',
                ...$role1,
                'linked_roles' => [DiscordRole::jsonStructure($linkedRole1)],
            ]),
            AchievementRole::jsonStructure([
                'lb_format' => $this->format1->id,
                'lb_type' => 'points',
                ...$role2,
                'linked_roles' => [DiscordRole::jsonStructure($linkedRole2)],
            ]),
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('put')]
    public function test_only_updates_specific_format_type()
    {
        $user = $this->createUserWithPermissions([
            $this->format1->id => ['edit:achievement_roles'],
            $this->format2->id => ['edit:achievement_roles']
        ]);

        $existingRole = AchievementRole::factory()->create([
            'lb_format' => $this->format2->id,
            'lb_type' => 'points',
        ]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [
                'lb_format' => $this->format1->id,
                'lb_type' => 'points',
                'roles' => [$this->makeSampleRole(100)]
            ])
            ->assertStatus(204);

        $actual = $this->getJson('/api/roles/achievement')
            ->assertStatus(200)
            ->json();

        $this->assertGreaterThan(0, count($actual));
        $this->assertTrue(
            collect($actual)
                ->contains(fn($role) => $role['lb_format'] === $existingRole->lb_format && $role['threshold'] === $existingRole->threshold)
        );
    }

    #[Group('put')]
    public function test_for_first_threshold_forced_to_zero()
    {
        $user = $this->createUserWithPermissions([$this->format1->id => ['edit:achievement_roles']]);

        $this->actingAs($user, 'discord')
            ->putJson('/api/roles/achievement', [
                'lb_format' => $this->format1->id,
                'lb_type' => 'points',
                'roles' => [$this->makeSampleRole(999, ['for_first' => true, 'threshold' => 999])]
            ])
            ->assertStatus(204);

        $actual = $this->getJson('/api/roles/achievement')
            ->assertStatus(200)
            ->json();

        $role = collect($actual)->first(fn($r) => $r['lb_format'] === $this->format1->id && $r['lb_type'] === 'points');
        $this->assertEquals(0, $role['threshold']);
        $this->assertTrue($role['for_first']);
    }
}
