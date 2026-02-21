<?php

namespace Tests\Helpers;

use App\Models\Config;
use Tests\TestCase;

/**
 * Test helpers for leaderboard point calculation.
 *
 * Provides helper methods to calculate leaderboard scores for testing purposes.
 * These mirror the backend calculation logic to verify correct implementation.
 *
 * Usage:
 *   $helper = new LeaderboardTestHelper($this);
 *   $points = $helper->calcMlUserPoints(123);
 */
class LeaderboardTestHelper
{
    protected TestCase $test;

    public function __construct(TestCase $test)
    {
        $this->test = $test;
    }

    /**
     * Calculate a user's maplist points.
     *
     * Fetches the user's completions and calculates total points based on:
     * - Base map points (from placement_curver for format 1, or placement_all for format 2)
     * - No Geraldo multiplier (if applicable)
     * - Black Border multiplier (if applicable)
     * - LCC bonus (if holding current LCC)
     *
     * Formula: <map points> * no geraldo multiplier (if no geraldo) * bb multiplier (if black border)
     * * (bb+no geraldo) (if both in separate completions) * bb*ng (if both in same completion)
     * + lcc bonus (if holding current lcc for the map)
     *
     * @param int $userId Discord ID of the user
     * @param int $formatId Format ID (1 = MAPLIST, 2 = MAPLIST_ALL_VERSIONS)
     * @return float Total points
     */
    public function calcMlUserPoints(int $userId, int $formatId = 1): float
    {
        // Fetch config
        $configNames = [
            'points_top_map',
            'points_bottom_map',
            'formula_slope',
            'points_extra_lcc',
            'points_multi_gerry',
            'points_multi_bb',
            'decimal_digits',
            'map_count',
        ];
        $config = Config::loadVars($configNames);

        // Fetch user's completions for the format
        $completions = $this->test->getJson("/api/completions?player_id={$userId}&format_id={$formatId}&per_page=150&include=map.metadata")
            ->assertStatus(200)
            ->json('data');

        // Group by map code
        $byMap = collect($completions)->groupBy('map.code');

        $points = 0.0;
        $placementKey = $formatId === 2 ? 'placement_all' : 'placement_curver';

        foreach ($byMap as $mapCompletions) {
            $mapCompletions = collect($mapCompletions);

            // Check what flags exist across all completions for this map
            $hasLcc = $mapCompletions->contains('is_current_lcc', true);
            $hasBb = $mapCompletions->contains('black_border', true);
            $hasNg = $mapCompletions->contains('no_geraldo', true);
            $hasBbAndNgSameCompletion = $mapCompletions->contains(fn($comp) => ($comp['black_border'] ?? false) && ($comp['no_geraldo'] ?? false));

            // Get map data (same for all completions on this map)
            $map = $mapCompletions->first()['map'];
            $placement = $map[$placementKey] ?? null;

            // Calculate base points from placement
            $mapCount = $config['map_count'];
            $bottomPts = $config['points_bottom_map'];
            $topPts = $config['points_top_map'];
            $slope = $config['formula_slope'];
            $decimalDigits = $config['decimal_digits'];

            if ($placement >= 1 && $placement <= $mapCount) {
                $exponent = pow(
                    (1 + (1 - $placement) / ($mapCount - 1)),
                    $slope
                );
                $basePts = $bottomPts * pow(($topPts / $bottomPts), $exponent);
                $basePts = round($basePts, $decimalDigits);
            } else {
                $basePts = 0;
            }

            // Calculate multiplier based on flags
            $multiplier = 1;
            if ($hasBbAndNgSameCompletion) {
                $multiplier = $config['points_multi_bb'] * $config['points_multi_gerry'];
            } elseif ($hasBb && $hasNg) {
                $multiplier = $config['points_multi_bb'] + $config['points_multi_gerry'];
            } elseif ($hasBb) {
                $multiplier = $config['points_multi_bb'];
            } elseif ($hasNg) {
                $multiplier = $config['points_multi_gerry'];
            }

            // Add points: base * multiplier + LCC bonus
            $points += $basePts * $multiplier;
            if ($hasLcc) {
                $points += $config['points_extra_lcc'];
            }
        }

        return $points;
    }

    /**
     * Calculate a user's expert list points.
     *
     * Fetches the user's completions and calculates total points based on:
     * - Base difficulty points (casual/medium/high/true/extreme)
     * - No Geraldo bonus points
     * - Black Border multiplier
     * - LCC bonus
     *
     * @param int $userId Discord ID of the user
     * @return float Total points
     */
    public function calcExpUserPoints(int $userId): float
    {
        // Fetch config
        $configNames = [
            'exp_points_casual',
            'exp_points_medium',
            'exp_points_high',
            'exp_points_true',
            'exp_points_extreme',
            'exp_nogerry_points_casual',
            'exp_nogerry_points_medium',
            'exp_nogerry_points_high',
            'exp_nogerry_points_true',
            'exp_nogerry_points_extreme',
            'exp_bb_multi',
            'exp_lcc_extra',
        ];
        $config = Config::loadVars($configNames);

        // Fetch user's completions for format 51 (EXPERT_LIST)
        $completions = $this->test->getJson("/api/completions?player_id={$userId}&format_id=51&per_page=150&include=map.metadata")
            ->assertStatus(200)
            ->json('data');

        // Group by map code
        $byMap = collect($completions)->groupBy('map.code');

        $configKeys = [
            'exp_points_casual',
            'exp_points_medium',
            'exp_points_high',
            'exp_points_true',
            'exp_points_extreme',
        ];

        $configKeysNoGerry = [
            'exp_nogerry_points_casual',
            'exp_nogerry_points_medium',
            'exp_nogerry_points_high',
            'exp_nogerry_points_true',
            'exp_nogerry_points_extreme',
        ];

        $points = 0.0;

        foreach ($byMap as $mapCompletions) {
            $mapCompletions = collect($mapCompletions);

            // Check what flags exist across all completions for this map
            $hasLcc = $mapCompletions->contains('current_lcc', true);
            $hasBb = $mapCompletions->contains('black_border', true);
            $hasNg = $mapCompletions->contains('no_geraldo', true);

            // Get map data (same for all completions on this map)
            $map = $mapCompletions->first()['map'];
            $difficulty = $map['difficulty'];

            // Base points from difficulty
            $basePoints = $config[$configKeys[$difficulty]];
            $points += $basePoints;

            // No Geraldo bonus
            if ($hasNg) {
                $points += $config[$configKeysNoGerry[$difficulty]];
            }

            // Black Border multiplier (adds base_points * (bb_multi - 1))
            if ($hasBb) {
                $bbMulti = $config['exp_bb_multi'];
                $points += $basePoints * ($bbMulti - 1);
            }

            // LCC bonus
            if ($hasLcc) {
                $points += $config['exp_lcc_extra'];
            }
        }

        return $points;
    }

    /**
     * Get a user's score from a specific leaderboard.
     *
     * @param int $userId Discord ID of the user
     * @param int $formatId Format ID
     * @param string $value Leaderboard value type (e.g., 'points', 'lccs', 'no_geraldo', 'black_border')
     * @return float The user's score
     * @throws \RuntimeException If user not found in leaderboard
     */
    public function getLbScore(int $userId, int $formatId, string $value): float
    {
        $response = $this->test->getJson("/api/formats/{$formatId}/leaderboard?value={$value}")
            ->assertStatus(200)
            ->json();

        $data = $response['data'] ?? [];

        foreach ($data as $entry) {
            if (($entry['user']['discord_id'] ?? null) == $userId) {
                return (float) ($entry['score'] ?? 0);
            }
        }

        throw new \RuntimeException("User {$userId} not found in leaderboard for format {$formatId}, value {$value}");
    }
}
