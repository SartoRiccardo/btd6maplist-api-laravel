<?php

namespace Tests\Feature\Roles;

use App\Models\AchievementRole;
use App\Models\DiscordRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementRoleGetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test getting achievement roles with no roles returns empty array.
     */
    public function test_get_achievement_roles_empty_returns_empty_array(): void
    {
        $actual = $this->getJson('/api/roles/achievement')
            ->assertStatus(200)
            ->json();

        $this->assertEquals([], $actual);
    }

    /**
     * Test getting achievement roles returns correct structure with multiple roles and discord roles.
     */
    public function test_get_achievement_roles_returns_correct_structure(): void
    {
        [$role1, $role2] = AchievementRole::factory()
            ->count(2)
            ->sequence(
                ['for_first' => true, 'threshold' => 0],
                ['for_first' => false, 'threshold' => 100],
            )
            ->create();

        $dr1 = DiscordRole::factory()->forAchievement($role1)->count(2)->create();
        $dr2 = DiscordRole::factory()->forAchievement($role2)->count(2)->create();

        $actual = $this->getJson('/api/roles/achievement')
            ->assertStatus(200)
            ->json();

        $expected = [
            AchievementRole::jsonStructure([
                ...$role1->toArray(),
                'linked_roles' => $dr1->map(fn($dr) => DiscordRole::jsonStructure($dr->toArray()))
                    ->toArray(),
            ]),
            AchievementRole::jsonStructure([
                ...$role2->toArray(),
                'linked_roles' => $dr2->map(fn($dr) => DiscordRole::jsonStructure($dr->toArray()))
                    ->toArray(),
            ]),
        ];

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test getting achievement roles with role having no discord roles.
     */
    public function test_get_achievement_roles_with_no_discord_roles(): void
    {
        $role = AchievementRole::factory()->create();

        $actual = $this->getJson('/api/roles/achievement')
            ->assertStatus(200)
            ->json();

        $expected = [
            AchievementRole::jsonStructure([
                ...$role->toArray(),
                'linked_roles' => [],
            ]),
        ];

        $this->assertEquals($expected, $actual);
    }
}
