<?php

namespace App\Traits;

trait TestableStructure
{
    /**
     * Get the JSON structure for testing purposes.
     *
     * @param array $overrides Fields to override (merged with defaults)
     * @param bool $strict If true, only fields in strictFields() will be included
     * @param array $exclude Keys to exclude from the result (even if in strict fields)
     * @return array
     */
    public static function jsonStructure(array $overrides = [], bool $strict = true, array $exclude = []): array
    {
        $defaults = [
            ...static::defaults($overrides),
            ...$overrides,
        ];

        if ($strict) {
            $allowedFields = array_flip(static::strictFields());
            $defaults = array_intersect_key($defaults, $allowedFields);
        }

        if (!empty($exclude)) {
            $defaults = array_diff_key($defaults, array_flip($exclude));
        }

        return $defaults;
    }

    /**
     * Get the default values for this model's JSON structure.
     * Should be overridden in the using class.
     *
     * @param array $overrides
     * @return array
     */
    abstract protected static function defaults(array $overrides = []): array;

    /**
     * Get the fields that are allowed when strict mode is enabled.
     * Should be overridden in the using class.
     *
     * @return array
     */
    abstract protected static function strictFields(): array;
}
