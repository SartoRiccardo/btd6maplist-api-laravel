<?php

namespace Tests\Feature\Roles;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test getting roles returns correct structure.
     */
    public function test_get_roles_returns_correct_structure(): void
    {
        Role::query()->delete();

        [$role1, $role2, $role3] = Role::factory()
            ->count(3)
            ->create();
        $role2->grantedBy()->attach($role1->id);

        $actual = $this->getJson('/api/roles')
            ->assertStatus(200)
            ->json();

        $expected = [
            Role::jsonStructure([
                ...$role1->toArray(),
                'can_grant' => [$role2->id],
            ]),
            Role::jsonStructure($role2->toArray()),
            Role::jsonStructure($role3->toArray()),
        ];

        $this->assertEquals($expected, $actual);
    }

    /**
     * Test getting roles excludes internal roles.
     */
    public function test_get_roles_excludes_internal_roles(): void
    {
        Role::query()->delete();

        $publicRole = Role::factory()->create();
        Role::factory()->create(['internal' => true]);

        $actual = $this->getJson('/api/roles')
            ->assertStatus(200)
            ->json();

        $expected = [
            Role::jsonStructure($publicRole->toArray()),
        ];

        $this->assertEquals($expected, $actual);
    }
}
