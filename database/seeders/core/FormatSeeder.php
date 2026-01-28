<?php

namespace Database\Seeders\Core;

use App\Constants\FormatConstants;
use App\Models\Format;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormatSeeder extends Seeder
{
    use WithoutModelEvents;

    private static array $formats = [
        FormatConstants::MAPLIST => [
            'name' => 'Maplist',
            'hidden' => false,
            'run_submission_status' => 1,
            'map_submission_status' => 2,
        ],
        FormatConstants::MAPLIST_ALL_VERSIONS => [
            'name' => 'Maplist (all versions)',
            'hidden' => true,
            'run_submission_status' => 0,
            'map_submission_status' => 0,
        ],
        FormatConstants::EXPERT_LIST => [
            'name' => 'Expert List',
            'hidden' => false,
            'run_submission_status' => 1,
            'map_submission_status' => 2,
        ],
        FormatConstants::BEST_OF_THE_BEST => [
            'name' => 'Best of the Best',
            'hidden' => false,
            'run_submission_status' => 1,
            'map_submission_status' => 0,
        ],
        FormatConstants::NOSTALGIA_PACK => [
            'name' => 'Nostalgia Pack',
            'hidden' => false,
            'run_submission_status' => 2,
            'map_submission_status' => 1,
        ],
    ];

    public function run(): void
    {
        foreach (self::$formats as $id => $format) {
            Format::updateOrCreate(
                ['id' => $id],
                array_merge(['id' => $id], $format)
            );
        }
    }
}
