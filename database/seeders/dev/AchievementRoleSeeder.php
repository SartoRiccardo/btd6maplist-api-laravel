<?php

namespace Database\Seeders\Dev;

use App\Constants\FormatConstants;
use App\Models\AchievementRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AchievementRoleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding achievement roles...');

        $roles = [
            // Maplist Points (1 = "maplist")
            // Based on real data: max 377, top players around 300-350
            [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'points',
                'threshold' => 0,
                'for_first' => true,
                'tooltip_description' => 'First Place',
                'name' => 'Maplist Champion',
                'clr_border' => 0xFFD700, // Gold
                'clr_inner' => 0xFFA500, // Orange
            ],
            [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'points',
                'threshold' => 1,
                'for_first' => false,
                'tooltip_description' => '1+ points',
                'name' => 'Maplist Bronze',
                'clr_border' => 0xCD7F32, // Bronze
                'clr_inner' => 0x8B4513, // SaddleBrown
            ],
            [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'points',
                'threshold' => 50,
                'for_first' => false,
                'tooltip_description' => '50+ points',
                'name' => 'Maplist Silver',
                'clr_border' => 0xC0C0C0, // Silver
                'clr_inner' => 0x808080, // Gray
            ],
            [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'points',
                'threshold' => 100,
                'for_first' => false,
                'tooltip_description' => '100+ points',
                'name' => 'Maplist Gold',
                'clr_border' => 0xFFD700, // Gold
                'clr_inner' => 0xFFA500, // Orange
            ],
            [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'points',
                'threshold' => 200,
                'for_first' => false,
                'tooltip_description' => '200+ points',
                'name' => 'Maplist Platinum',
                'clr_border' => 0xE5E4E2, // Platinum
                'clr_inner' => 0x00CED1, // DarkTurquoise
            ],
            [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'points',
                'threshold' => 300,
                'for_first' => false,
                'tooltip_description' => '300+ points',
                'name' => 'Maplist Diamond',
                'clr_border' => 0xB9F2FF, // LightBlue
                'clr_inner' => 0x00BFFF, // DeepSkyBlue
            ],
            [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'points',
                'threshold' => 350,
                'for_first' => false,
                'tooltip_description' => '350+ points',
                'name' => 'Maplist Elite',
                'clr_border' => 0xFF00FF, // Magenta
                'clr_inner' => 0x8B008B, // DarkMagenta
            ],

            // Maplist Black Border
            [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'black_border',
                'threshold' => 0,
                'for_first' => true,
                'tooltip_description' => 'First Place',
                'name' => 'BB Champion',
                'clr_border' => 0x000000, // Black
                'clr_inner' => 0x2F4F4F, // DarkSlateGray
            ],
            [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'black_border',
                'threshold' => 1,
                'for_first' => false,
                'tooltip_description' => '1+ Black Border',
                'name' => 'BB Starter',
                'clr_border' => 0x2F4F4F, // DarkSlateGray
                'clr_inner' => 0x696969, // DimGray
            ],
            [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'black_border',
                'threshold' => 10,
                'for_first' => false,
                'tooltip_description' => '10+ Black Borders',
                'name' => 'BB Expert',
                'clr_border' => 0x000000, // Black
                'clr_inner' => 0x00008B, // DarkBlue
            ],
            [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'black_border',
                'threshold' => 25,
                'for_first' => false,
                'tooltip_description' => '25+ Black Borders',
                'name' => 'BB Master',
                'clr_border' => 0x000000, // Black
                'clr_inner' => 0x4B0082, // Indigo
            ],

            // Maplist No Geraldo
            [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'no_geraldo',
                'threshold' => 0,
                'for_first' => true,
                'tooltip_description' => 'First Place',
                'name' => 'NG Champion',
                'clr_border' => 0x00FF00, // Lime
                'clr_inner' => 0x228B22, // ForestGreen
            ],
            [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'no_geraldo',
                'threshold' => 1,
                'for_first' => false,
                'tooltip_description' => '1+ No Geraldo',
                'name' => 'NG Starter',
                'clr_border' => 0x32CD32, // LimeGreen
                'clr_inner' => 0x006400, // DarkGreen
            ],
            [
                'lb_format' => FormatConstants::MAPLIST,
                'lb_type' => 'no_geraldo',
                'threshold' => 10,
                'for_first' => false,
                'tooltip_description' => '10+ No Geraldo',
                'name' => 'NG Expert',
                'clr_border' => 0x00FF00, // Lime
                'clr_inner' => 0x008000, // Green
            ],

            // Expert List (51 = "experts")
            // Based on real data: max 75, top players around 50-75
            [
                'lb_format' => FormatConstants::EXPERT_LIST,
                'lb_type' => 'points',
                'threshold' => 0,
                'for_first' => true,
                'tooltip_description' => 'First Place',
                'name' => 'Expert Champion',
                'clr_border' => 0xFF4500, // OrangeRed
                'clr_inner' => 0xFF0000, // Red
            ],
            [
                'lb_format' => FormatConstants::EXPERT_LIST,
                'lb_type' => 'points',
                'threshold' => 1,
                'for_first' => false,
                'tooltip_description' => '1+ points',
                'name' => 'Expert Bronze',
                'clr_border' => 0xCD7F32, // Bronze
                'clr_inner' => 0x8B4513, // SaddleBrown
            ],
            [
                'lb_format' => FormatConstants::EXPERT_LIST,
                'lb_type' => 'points',
                'threshold' => 10,
                'for_first' => false,
                'tooltip_description' => '10+ points',
                'name' => 'Expert Silver',
                'clr_border' => 0xC0C0C0, // Silver
                'clr_inner' => 0x808080, // Gray
            ],
            [
                'lb_format' => FormatConstants::EXPERT_LIST,
                'lb_type' => 'points',
                'threshold' => 25,
                'for_first' => false,
                'tooltip_description' => '25+ points',
                'name' => 'Expert Gold',
                'clr_border' => 0xFFD700, // Gold
                'clr_inner' => 0xFFA500, // Orange
            ],
            [
                'lb_format' => FormatConstants::EXPERT_LIST,
                'lb_type' => 'points',
                'threshold' => 40,
                'for_first' => false,
                'tooltip_description' => '40+ points',
                'name' => 'Expert Platinum',
                'clr_border' => 0xE5E4E2, // Platinum
                'clr_inner' => 0x00CED1, // DarkTurquoise
            ],
            [
                'lb_format' => FormatConstants::EXPERT_LIST,
                'lb_type' => 'points',
                'threshold' => 50,
                'for_first' => false,
                'tooltip_description' => '50+ points',
                'name' => 'Expert Diamond',
                'clr_border' => 0xB9F2FF, // LightBlue
                'clr_inner' => 0x00BFFF, // DeepSkyBlue
            ],
            [
                'lb_format' => FormatConstants::EXPERT_LIST,
                'lb_type' => 'points',
                'threshold' => 65,
                'for_first' => false,
                'tooltip_description' => '65+ points',
                'name' => 'Expert Elite',
                'clr_border' => 0xFF00FF, // Magenta
                'clr_inner' => 0x8B008B, // DarkMagenta
            ],
        ];

        foreach ($roles as $role) {
            AchievementRole::insertOrIgnore($role);
        }

        $this->command->info('Created ' . count($roles) . ' achievement role entries.');
    }
}
