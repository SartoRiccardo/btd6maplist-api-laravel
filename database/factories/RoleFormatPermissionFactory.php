<?php

namespace Database\Factories;

use App\Models\RoleFormatPermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoleFormatPermission>
 */
class RoleFormatPermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => 0,
            'format_id' => null,
            'permission' => 'edit:config',
        ];
    }

    /**
     * Set the permission for a specific format.
     *
     * @param string $permission The permission name
     * @param \App\Models\Format|int|null|string $format Either a Format model, format ID, or null for global
     */
    public function permission(string $permission, \App\Models\Format|int|null|string $format = null): self
    {
        if ($format === '') {
            $format = null;
        }

        $formatId = is_int($format) ? $format : $format?->id;

        return $this->state(fn(array $attributes) => [
            'permission' => $permission,
            'format_id' => $formatId,
        ]);
    }
}
