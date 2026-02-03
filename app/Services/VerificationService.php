<?php

namespace App\Services;

use App\Constants\FormatConstants;
use App\Models\CompletionMeta;
use App\Models\Config;
use App\Models\Verification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerificationService
{
    /**
     * Create verifications for all formats except MAPLIST_ALL_VERSIONS.
     * This replaces the tr_set_verif_on_accept trigger.
     */
    public function createVerificationsForCompletion(CompletionMeta $completionMeta): void
    {
        // Skip for MAPLIST_ALL_VERSIONS
        if ($completionMeta->format_id === FormatConstants::MAPLIST_ALL_VERSIONS) {
            return;
        }

        $completionMeta->load('completion.map', 'players');
        $mapCode = $completionMeta->completion->map->code;
        $currentBtd6Ver = (int) Config::where('name', 'current_btd6_ver')->first()->value;

        DB::transaction(function () use ($completionMeta, $mapCode, $currentBtd6Ver) {
            // Current version verifier
            $exists = Verification::where('map_code', $mapCode)
                ->where('version', $currentBtd6Ver)
                ->exists();

            if (!$exists) {
                foreach ($completionMeta->players as $player) {
                    Verification::firstOrCreate([
                        'map_code' => $mapCode,
                        'user_id' => $player->discord_id,
                        'version' => $currentBtd6Ver,
                    ]);
                }
                Log::info('Created current version verifications', [
                    'map' => $mapCode,
                    'version' => $currentBtd6Ver,
                ]);
            }

            // First time verifier
            $exists = Verification::where('map_code', $mapCode)
                ->whereNull('version')
                ->exists();

            if (!$exists) {
                foreach ($completionMeta->players as $player) {
                    Verification::firstOrCreate([
                        'map_code' => $mapCode,
                        'user_id' => $player->discord_id,
                        'version' => null,
                    ]);
                }
                Log::info('Created first time verifications', [
                    'map' => $mapCode,
                ]);
            }
        });
    }
}
