<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\RoleGrant;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleGrantFactory extends Factory
{
    protected $model = RoleGrant::class;

    public function definition(): array
    {
        return [
            'role_required' => Role::factory(),
            'role_can_grant' => Role::factory(),
        ];
    }
}
