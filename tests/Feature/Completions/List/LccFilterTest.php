<?php

namespace Tests\Feature\Completions\List;

use App\Models\CompletionMeta;
use App\Models\LeastCostChimps;
use Database\Factories\CompletionMetaFactory;

class LccFilterTest extends ThreeStateFilterTestBase
{
    protected function getFilterName(): string
    {
        return 'lcc';
    }

    protected function createIncludedMetaFactory(): CompletionMetaFactory
    {
        $lcc = LeastCostChimps::factory()->create();
        return CompletionMeta::factory()->state(['lcc_id' => $lcc->id]);
    }

    protected function createExcludedMetaFactory(): CompletionMetaFactory
    {
        return CompletionMeta::factory()->state(['lcc_id' => null]);
    }
}
