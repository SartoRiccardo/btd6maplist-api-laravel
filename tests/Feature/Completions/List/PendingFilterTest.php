<?php

namespace Tests\Feature\Completions\List;

use App\Models\CompletionMeta;
use Database\Factories\CompletionMetaFactory;

class PendingFilterTest extends ThreeStateFilterTestBase
{
    protected function getFilterName(): string
    {
        return 'pending';
    }

    protected function createIncludedMetaFactory(): CompletionMetaFactory
    {
        return CompletionMeta::factory()->state(['accepted_by_id' => null]);
    }

    protected function createExcludedMetaFactory(): CompletionMetaFactory
    {
        return CompletionMeta::factory()->accepted();
    }
}
