<?php

namespace Tests\Feature\Formats\List;

use App\Models\Format;
use Tests\Helpers\FormatTestHelper;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    #[Group('get')]
    #[Group('formats')]
    #[Group('pagination')]
    public function test_returns_formats_with_default_pagination(): void
    {
        Format::truncate();

        $count = 15;
        Format::factory()->count($count)->create();

        $actual = $this->getJson('/api/formats')
            ->assertStatus(200)
            ->json();

        // Get formats sorted by ID to match API response
        $formats = Format::orderBy('id')->take($count)->get();
        $expected = FormatTestHelper::expectedFormatList($formats);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('formats')]
    #[Group('pagination')]
    public function test_returns_formats_with_custom_pagination(): void
    {
        Format::truncate();

        $total = 25;
        $page = 2;
        $perPage = 10;

        Format::factory()->count($total)->create();

        $actual = $this->getJson("/api/formats?page={$page}&per_page={$perPage}")
            ->assertStatus(200)
            ->json();

        // Get formats sorted by ID to match API response
        $allFormats = Format::orderBy('id')->get();
        $pageFormats = $allFormats->forPage($page, $perPage)->values();

        $expected = FormatTestHelper::expectedFormatList($pageFormats, [
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
            'per_page' => $perPage,
            'total' => $total,
        ]);

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('formats')]
    #[Group('pagination')]
    public function test_returns_empty_array_on_page_overflow(): void
    {
        Format::truncate();

        $count = 5;
        Format::factory()->count($count)->create();

        $actual = $this->getJson('/api/formats?page=999')
            ->assertStatus(200)
            ->json();

        $expected = [
            'data' => [],
            'meta' => [
                'current_page' => 999,
                'last_page' => 1,
                'per_page' => 100,
                'total' => $count,
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('formats')]
    #[Group('pagination')]
    public function test_caps_per_page_at_maximum(): void
    {
        $this->getJson('/api/formats?per_page=101')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('per_page');
    }
}
