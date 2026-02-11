<?php

namespace Tests\Feature\Roles\Platform;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\RoleTestHelper;
use Tests\TestCase;

class IndexTest extends TestCase
{
    #[Group('get')]
    #[Group('roles')]
    #[Group('platform')]
    public function test_returns_platform_roles_with_can_grant(): void
    {
        Role::query()->delete();
        DB::table('role_grants')->truncate();

        // Create some roles to be used as grantable targets
        $grantableRole1 = Role::factory()->create();
        $grantableRole2 = Role::factory()->create();

        // Create platform roles with can_grant relationships
        $platformRole1 = Role::factory()->internal()->canGrant([$grantableRole1->id, $grantableRole2->id])->create();
        $platformRole2 = Role::factory()->internal()->canGrant([$grantableRole1->id])->create();
        $platformRole3 = Role::factory()->internal()->create();

        // Create non-platform roles - should NOT appear in results
        Role::factory()->count(2)->create();

        $platformRole1->load('canGrant');
        $platformRole2->load('canGrant');
        $platformRole3->load('canGrant');

        $actual = $this->getJson('/api/roles/platform')
            ->assertStatus(200)
            ->json();

        $expected = RoleTestHelper::expectedPlatformRoleList(
            collect([$platformRole1, $platformRole2, $platformRole3])->sortBy('id')->values()
        );

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('roles')]
    #[Group('platform')]
    public function test_returns_empty_array_when_no_platform_roles_exist(): void
    {
        Role::query()->delete();
        DB::table('role_grants')->truncate();

        Role::factory()->count(3)->create();

        $actual = $this->getJson('/api/roles/platform')
            ->assertStatus(200)
            ->json();

        $expected = [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => 0,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('roles')]
    #[Group('platform')]
    #[Group('pagination')]
    public function test_returns_platform_roles_with_custom_pagination(): void
    {
        Role::query()->delete();
        DB::table('role_grants')->truncate();

        $roles = Role::factory()->count(5)->internal()->create();

        $page = 2;
        $perPage = 2;

        $pageRoles = $roles->sortBy('id')->forPage($page, $perPage)->values();

        $actual = $this->getJson("/api/roles/platform?page={$page}&per_page={$perPage}")
            ->assertStatus(200)
            ->json();

        $expected = RoleTestHelper::expectedPlatformRoleList($pageRoles, [
            'current_page' => $page,
            'last_page' => (int) ceil(5 / $perPage),
            'per_page' => $perPage,
            'total' => 5,
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('roles')]
    #[Group('platform')]
    #[Group('validation')]
    public function test_validates_per_page_maximum(): void
    {
        $this->getJson('/api/roles/platform?per_page=500')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('per_page');
    }
}
