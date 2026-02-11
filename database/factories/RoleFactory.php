<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\RoleGrant;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'id' => fake()->unique()->randomNumber(5, true),
            'name' => fake()->words(2, true),
            'assign_on_create' => false,
            'internal' => false,
        ];
    }

    public function assignOnCreate(): self
    {
        return $this->state(fn(array $attributes) => [
            'assign_on_create' => true,
        ]);
    }

    public function internal(): self
    {
        return $this->state(fn(array $attributes) => [
            'internal' => true,
        ]);
    }

    public function canGrant(array $grantableRoleIds): self
    {
        return $this->afterCreating(function (Role $role) use ($grantableRoleIds) {
            RoleGrant::factory()
                ->count(count($grantableRoleIds))
                ->sequence(fn($seq) => ['role_can_grant' => $grantableRoleIds[$seq->index]])
                ->create(['role_required' => $role->id]);
        });
    }
}
