<?php

namespace Tests\Feature\Formats\List;

use App\Constants\FormatConstants;
use App\Models\Format;
use Tests\Helpers\FormatTestHelper;
use Tests\TestCase;

class EmptyProposedDifficultiesTest extends TestCase
{
    #[Group('get')]
    #[Group('formats')]
    #[Group('response')]
    public function test_formats_with_null_proposed_difficulties(): void
    {
        Format::truncate();

        // Nostalgia Pack (ID 11) returns null for proposed_difficulties
        Format::factory()->create([
            'id' => FormatConstants::NOSTALGIA_PACK,
            'name' => 'Nostalgia Pack',
            'proposed_difficulties' => null,
        ]);

        $actual = $this->getJson('/api/formats')
            ->assertStatus(200)
            ->json();

        $this->assertNull($actual['data'][0]['proposed_difficulties']);
    }

    #[Group('get')]
    #[Group('formats')]
    #[Group('response')]
    public function test_formats_with_proposed_difficulties_array(): void
    {
        Format::truncate();

        // Maplist (ID 1) returns an array of proposed difficulties
        Format::factory()->create([
            'id' => FormatConstants::MAPLIST,
            'name' => 'Maplist',
            'proposed_difficulties' => ["Top 3", "Top 10", "#11 ~ 20", "#21 ~ 30", "#31 ~ 40", "#41 ~ 50"],
        ]);

        $actual = $this->getJson('/api/formats')
            ->assertStatus(200)
            ->json();

        $this->assertIsArray($actual['data'][0]['proposed_difficulties']);
        $this->assertEqualsCanonicalizing(
            ["Top 3", "Top 10", "#11 ~ 20", "#21 ~ 30", "#31 ~ 40", "#41 ~ 50"],
            $actual['data'][0]['proposed_difficulties']
        );
    }
}
