<?php

namespace Tests\Helpers;

use App\Models\Format;
use Illuminate\Database\Eloquent\Collection;

class FormatTestHelper
{
    public static function expectedFormatList(Collection $formats, array $metaOverrides = []): array
    {
        $meta = [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 100,
            'total' => $formats->count(),
            ...$metaOverrides,
        ];

        return [
            'data' => $formats->map(fn($format) => Format::jsonStructure($format->toArray()))->toArray(),
            'meta' => $meta,
        ];
    }
}
