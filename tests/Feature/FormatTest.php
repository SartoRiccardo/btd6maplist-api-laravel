<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use PHPUnit\Attributes\Group;
use Tests\TestCase;

/**
 * Test getting all formats.
 *
 * @author rikki.sarto@gmail.com
 */
class FormatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Group('get')]
    public function test_get_formats_returns_all_formats_in_correct_order(): void
    {
        $actual = $this->getJson('/api/formats')
            ->assertStatus(200)
            ->json();

        $expected = [
            [
                'id' => 1,
                'name' => 'Maplist',
                'hidden' => false,
                'map_submission_status' => 'open_chimps',
                'run_submission_status' => 'open',
                'proposed_difficulties' => ['Top 3', 'Top 10', '#11 ~ 20', '#21 ~ 30', '#31 ~ 40', '#41 ~ 50'],
            ],
            [
                'id' => 2,
                'name' => 'Maplist (all versions)',
                'hidden' => true,
                'map_submission_status' => 'closed',
                'run_submission_status' => 'closed',
                'proposed_difficulties' => ['Top 3', 'Top 10', '#11 ~ 20', '#21 ~ 30', '#31 ~ 40', '#41 ~ 50'],
            ],
            [
                'id' => 11,
                'name' => 'Nostalgia Pack',
                'hidden' => false,
                'map_submission_status' => 'open',
                'run_submission_status' => 'lcc_only',
                'proposed_difficulties' => null,
            ],
            [
                'id' => 51,
                'name' => 'Expert List',
                'hidden' => false,
                'map_submission_status' => 'open_chimps',
                'run_submission_status' => 'open',
                'proposed_difficulties' => [
                    'Casual Expert',
                    'Casual/Medium Expert',
                    'Medium Expert',
                    'Medium/High Expert',
                    'High Expert',
                    'High/True Expert',
                    'True Expert',
                    'True/Extreme Expert',
                    'Extreme Expert',
                ],
            ],
            [
                'id' => 52,
                'name' => 'Best of the Best',
                'hidden' => false,
                'map_submission_status' => 'closed',
                'run_submission_status' => 'open',
                'proposed_difficulties' => ['Beginner', 'Intermediate', 'Advanced', 'Expert/Extreme'],
            ],
        ];

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    public function test_formats_endpoint_is_public(): void
    {
        $this->getJson('/api/formats')
            ->assertStatus(200);
    }
}
