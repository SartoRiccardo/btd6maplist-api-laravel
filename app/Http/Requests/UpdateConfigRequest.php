<?php

namespace App\Http\Requests;

use App\Models\Config;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="UpdateConfigRequest",
 *     required={"config"},
 *     @OA\Property(
 *         property="config",
 *         type="object",
 *         description="Object mapping config variable names to their new values",
 *         additionalProperties={
 *             "one"={@OA\Schema(type="integer")},
 *             "two"={@OA\Schema(type="number")},
 *             "three"={@OA\Schema(type="string")}
 *         },
 *         example={"points_top_map": 150, "points_bottom_map": 10}
 *     )
 * )
 */
class UpdateConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'config' => 'required|array',
            'config.*' => 'present',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $config = $this->input('config', []);
            if (empty($config)) {
                return;
            }

            // Single query to get all configs
            $configs = Config::whereIn('name', array_keys($config))
                ->get()
                ->keyBy('name');

            // Check all keys exist
            $invalidKeys = array_diff(array_keys($config), $configs->keys()->toArray());
            foreach ($invalidKeys as $key) {
                $validator->errors()->add("config.{$key}", 'Invalid key');
            }
            if (count($invalidKeys)) {
                return;
            }

            foreach ($config as $key => $value) {
                $configModel = $configs->get($key);
                if (!$configModel) {
                    continue;
                }

                $expectedType = $configModel->type;

                if ($expectedType === 'int') {
                    if (!is_numeric($value) || (string) (int) $value !== (string) $value) {
                        $validator->errors()->add("config.{$key}", 'Must be of type int');
                    }
                } elseif ($expectedType === 'float') {
                    if (!is_numeric($value)) {
                        $validator->errors()->add("config.{$key}", 'Must be of type float');
                    }
                } elseif ($expectedType === 'string') {
                    if (!is_string($value)) {
                        $validator->errors()->add("config.{$key}", 'Must be of type string');
                    }
                }
            }
        });
    }
}
