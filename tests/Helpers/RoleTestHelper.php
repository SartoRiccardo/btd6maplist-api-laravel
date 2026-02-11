<?php

namespace Tests\Helpers;

use App\Models\Role;
use Illuminate\Support\Collection;

class RoleTestHelper
{
    public static function expectedPlatformRoleList(Collection $roles, array $metaOverrides = []): array
    {
        $meta = [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 100,
            'total' => $roles->count(),
            ...$metaOverrides,
        ];

        return [
            'data' => $roles->map(fn($role) => Role::jsonStructure([
                'id' => $role->id,
                'name' => $role->name,
                'internal' => $role->internal,
                'can_grant' => $role->can_grant ?? [],
            ]))->toArray(),
            'meta' => $meta,
        ];
    }
}
