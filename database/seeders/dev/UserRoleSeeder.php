<?php

namespace Database\Seeders\Dev;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserRoleSeeder extends Seeder
{
    use WithoutModelEvents;

    private const int USERS_TO_ASSIGN = 50;

    private \Faker\Generator $faker;

    public function __construct()
    {
        $this->faker = \Faker\Factory::create();
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = Role::all();

        if ($roles->isEmpty()) {
            $this->command->error('No roles found. Please seed roles first.');
            return;
        }

        // Get users who have no roles assigned
        $usersWithoutRoles = User::whereDoesntHave('roles')
            ->inRandomOrder()
            ->limit(self::USERS_TO_ASSIGN)
            ->get();

        if ($usersWithoutRoles->isEmpty()) {
            $this->command->warn('No users found without roles.');
            return;
        }

        $this->command->info("Assigning roles to {$usersWithoutRoles->count()} users...");

        foreach ($usersWithoutRoles as $user) {
            $roleCount = $this->determineRoleCount();

            if ($roleCount === 0) {
                // 10% chance of no role
                continue;
            }

            $selectedRoles = $roles->random(min($roleCount, $roles->count()));

            foreach ($selectedRoles as $role) {
                $user->roles()->attach($role->id);
            }
        }

        $this->command->info('User roles seeded successfully.');
    }

    /**
     * Determine number of roles: 10% 0, 70% 1, 15% 2, 5% 3.
     */
    private function determineRoleCount(): int
    {
        $roll = $this->faker->numberBetween(1, 100);

        if ($roll <= 10) {
            return 0;
        } elseif ($roll <= 80) {
            return 1;
        } elseif ($roll <= 95) {
            return 2;
        }

        return 3;
    }
}
