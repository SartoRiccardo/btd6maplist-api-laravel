<?php

namespace App\Constants;

class FormatConstants
{
    public const int MAPLIST = 1;
    public const int MAPLIST_ALL_VERSIONS = 2;
    public const int NOSTALGIA_PACK = 11;
    public const int EXPERT_LIST = 51;
    public const int BEST_OF_THE_BEST = 52;

    /**
     * Format ID to leaderboard name mapping.
     */
    public const LEADERBOARD_NAMES = [
        self::MAPLIST => 'maplist',
        self::MAPLIST_ALL_VERSIONS => 'maplist_all',
        self::EXPERT_LIST => 'experts',
    ];

    /**
     * Allowed leaderboard types.
     */
    public const LEADERBOARD_TYPES = ['points', 'lccs', 'no_geraldo', 'black_border'];

    /**
     * Leaderboard function mapping for non-points types.
     */
    public const LEADERBOARD_FUNCTIONS = [
        'lccs' => 'leaderboard_lccs',
        'no_geraldo' => 'leaderboard_no_geraldo',
        'black_border' => 'leaderboard_black_border',
    ];
}
