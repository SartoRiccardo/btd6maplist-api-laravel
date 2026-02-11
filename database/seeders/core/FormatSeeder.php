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
            'run_submission_status' => 'open',
            'map_submission_status' => 'open_chimps',
            'proposed_difficulties' => ["Top 3", "Top 10", "#11 ~ 20", "#21 ~ 30", "#31 ~ 40", "#41 ~ 50"],
        ],
        FormatConstants::MAPLIST_ALL_VERSIONS => [
            'name' => 'Maplist (all versions)',
            'hidden' => true,
            'run_submission_status' => 'closed',
            'map_submission_status' => 'closed',
            'proposed_difficulties' => ["Top 3", "Top 10", "#11 ~ 20", "#21 ~ 30", "#31 ~ 40", "#41 ~ 50"],
        ],
        FormatConstants::EXPERT_LIST => [
            'name' => 'Expert List',
            'hidden' => false,
            'run_submission_status' => 'open',
            'map_submission_status' => 'open_chimps',
            'proposed_difficulties' => ["Casual Expert", "Casual/Medium Expert", "Medium Expert", "Medium/High Expert", "High Expert", "High/True Expert", "True Expert", "True/Extreme Expert", "Extreme Expert"],
        ],
        FormatConstants::BEST_OF_THE_BEST => [
            'name' => 'Best of the Best',
            'hidden' => false,
            'run_submission_status' => 'lcc_only',
            'map_submission_status' => 'open',
            'proposed_difficulties' => ["Beginner", "Intermediate", "Advanced", "Expert/Extreme"],
        ],
        FormatConstants::NOSTALGIA_PACK => [
            'name' => 'Nostalgia Pack',
            'hidden' => false,
            'run_submission_status' => 'lcc_only',
            'map_submission_status' => 'open',
            'proposed_difficulties' => null,
        ],
    ];

    public function run(): void
    {
        foreach (self::$formats as $id => $format) {
            Format::updateOrCreate(
                ['id' => $id],
                $format
            );
        }
    }
}
