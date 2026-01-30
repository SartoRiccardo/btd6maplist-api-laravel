<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use \App\Models\DiscordRole;

/**
 * @OA\Schema(
 *     schema="UpdateAchievementRolesRequest",
 *     required={"lb_format", "lb_type", "roles"},
 *     @OA\Property(property="lb_format", type="integer"),
 *     @OA\Property(property="lb_type", type="string", enum={"points","no_geraldo","black_border","lccs"}),
 *     @OA\Property(
 *         property="roles",
 *         type="array",
 *         @OA\Items(
 *             @OA\Property(property="threshold", type="integer"),
 *             @OA\Property(property="for_first", type="boolean"),
 *             @OA\Property(property="tooltip_description", type="string", nullable=true),
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="clr_border", type="integer"),
 *             @OA\Property(property="clr_inner", type="integer"),
 *             @OA\Property(
 *                 property="linked_roles",
 *                 type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="guild_id", type="string"),
 *                     @OA\Property(property="role_id", type="string")
 *                 )
 *             )
 *         )
 *     )
 * )
 */
class UpdateAchievementRolesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lb_format' => 'required|integer|exists:formats,id',
            'lb_type' => 'required|in:points,no_geraldo,black_border,lccs',
            'roles' => 'required|array|min:1',
            'roles.*.threshold' => 'required|integer|min:0',
            'roles.*.for_first' => 'required|boolean',
            'roles.*.tooltip_description' => 'nullable|string|max:128',
            'roles.*.name' => 'required|string|min:1|max:32',
            'roles.*.clr_border' => 'required|integer|min:0|max:16777215',
            'roles.*.clr_inner' => 'required|integer|min:0|max:16777215',
            'roles.*.linked_roles' => 'required|array|min:1',
            'roles.*.linked_roles.*.guild_id' => 'required|string|numeric',
            'roles.*.linked_roles.*.role_id' => 'required|string|numeric',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('roles')) {
            return;
        }

        $roles = $this->input('roles');
        foreach ($roles as &$role) {
            // Force threshold = 0 when for_first = true
            if (($role['for_first'] ?? false) === true) {
                $role['threshold'] = 0;
            }

            // Convert empty tooltip_description to null
            if (isset($role['tooltip_description']) && $role['tooltip_description'] === '') {
                $role['tooltip_description'] = null;
            }
        }

        $this->merge(['roles' => $roles]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $roles = $this->input('roles', []);
            $errors = [];
            $hasFirst = false;
            $thresholdCounts = [];
            $discordRoleIds = [];
            $discordRoleIndexes = [];

            foreach ($roles as $i => $role) {
                // Check: Only one for_first role allowed
                if ($role['for_first']) {
                    if ($hasFirst) {
                        $errors["roles.{$i}.for_first"] = 'Can only have one role for first place';
                    }
                    $hasFirst = true;
                }

                // Track thresholds for duplicate check
                $thresholdCounts[$role['threshold']][] = $i;

                // Track Discord role IDs for duplicate check
                foreach (($role['linked_roles'] ?? []) as $j => $dr) {
                    $roleId = $dr['role_id'] ?? '';

                    // Check: Numeric guild_id and role_id
                    if (!is_numeric($dr['guild_id'] ?? '')) {
                        $errors["roles.{$i}.linked_roles.{$j}.guild_id"] = 'Invalid Guild ID';
                    }
                    if (!is_numeric($roleId)) {
                        $errors["roles.{$i}.linked_roles.{$j}.role_id"] = 'Invalid Role ID';
                        continue;
                    }

                    // Check: Duplicate Discord role IDs within request
                    if (isset($discordRoleIds[$roleId])) {
                        $errors["roles.{$i}.linked_roles.{$j}.role_id"] = 'Duplicate Discord role ID';
                    } else {
                        $discordRoleIds[$roleId] = true;
                        $discordRoleIndexes[(int) $roleId] = [$i, $j];
                    }
                }
            }

            // Check: Duplicate thresholds
            foreach ($thresholdCounts as $threshold => $indexes) {
                if (count($indexes) > 1) {
                    foreach ($indexes as $i) {
                        $errors["roles.{$i}.threshold"] = 'Duplicate threshold';
                    }
                }
            }

            // Check: Discord role IDs used in other (lb_format, lb_type) combinations
            if (!empty($discordRoleIds) && empty($errors)) {
                $existingRoles = DiscordRole::whereIn('role_id', array_keys($discordRoleIds))
                    ->where(function ($q) {
                        $q->where('ar_lb_format', '!=', $this->input('lb_format'))
                            ->orWhere('ar_lb_type', '!=', $this->input('lb_type'));
                    })
                    ->pluck('role_id')
                    ->toArray();

                foreach ($existingRoles as $roleId) {
                    if (isset($discordRoleIndexes[$roleId])) {
                        [$i, $j] = $discordRoleIndexes[$roleId];
                        $errors["roles.{$i}.linked_roles.{$j}.role_id"] = 'This role is already used elsewhere!';
                    }
                }
            }

            foreach ($errors as $key => $message) {
                $validator->errors()->add($key, $message);
            }
        });
    }
}
