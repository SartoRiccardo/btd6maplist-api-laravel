<?php

namespace Tests\Feature\Config;

use App\Models\Config;
use App\Models\ConfigFormat;
use PHPUnit\Attributes\Group;
use Tests\TestCase;

/**
 * Test GET /config endpoint.
 */
class ConfigGetTest extends TestCase
{
    /**
     * Test getting all config variables successfully.
     */
    #[Group('get')]
    public function test_get_config_returns_all_variables(): void
    {
        ConfigFormat::query()->delete();
        Config::query()->delete();

        $cfg1 = Config::factory()->type('float')->forFormats([1, 2])->create(['value' => '100.0']);
        $cfg2 = Config::factory()->type('int')->forFormats([1, 2, 51])->create(['value' => '441']);

        $actual = $this->getJson('/api/config')
            ->assertStatus(200)
            ->json();

        $expected = [
            $cfg1->name => [
                'value' => 100.0,
                'formats' => [1, 2],
                'type' => 'float',
                'description' => $cfg1->description,
            ],
            $cfg2->name => [
                'value' => 441,
                'formats' => [1, 2, 51],
                'type' => 'int',
                'description' => $cfg2->description,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }
}
