<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Config",
 *     type="object",
 *     @OA\Property(property="value", type="mixed", description="The config value (cast to int/float/string based on type)"),
 *     @OA\Property(property="formats", type="array", items={ "type"="integer" }, description="Array of format IDs this config applies to"),
 *     @OA\Property(property="type", type="string", enum={"int", "float", "string"}, description="The type of the config value"),
 *     @OA\Property(property="description", type="string", description="Human-readable description of the config")
 * )
 */
class Config extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'config';

    protected $fillable = [
        'name',
        'value',
        'type',
        'created_on',
        'difficulty',
        'description',
    ];

    protected $appends = ['value', 'formats'];

    protected $hidden = ['configFormats', 'id', 'name', 'created_on', 'difficulty'];

    protected $casts = [
        'difficulty' => 'integer',
    ];

    public function configFormats()
    {
        return $this->hasMany(ConfigFormat::class, 'config_name', 'name');
    }

    /**
     * Get the casted value attribute.
     */
    protected function getValueAttribute(): mixed
    {
        return $this->castValue($this->attributes['value'], $this->type);
    }

    /**
     * Get the formats attribute as an array of format IDs.
     */
    protected function getFormatsAttribute(): array
    {
        return $this->configFormats->pluck('format_id')->sort()->values()->toArray();
    }

    /**
     * Cast a value to the appropriate type based on the config type.
     */
    public function castValue($value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    /**
     * Load multiple config values by name, returning a keyed collection with casted values.
     *
     * @param array $names Array of config names to load
     * @return \Illuminate\Support\Collection Keyed collection with config names as keys and casted values
     */
    public static function loadVars(array $names): \Illuminate\Support\Collection
    {
        return self::whereIn('name', $names)
            ->get()
            ->mapWithKeys(fn($config) => [$config->name => $config->value]);
    }
}
