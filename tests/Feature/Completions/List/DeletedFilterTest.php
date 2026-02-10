<?php

namespace Tests\Feature\Completions\List;

use App\Models\CompletionMeta;
use Database\Factories\CompletionMetaFactory;

class DeletedFilterTest extends ThreeStateFilterTestBase
{
    protected function getFilterName(): string
    {
        return 'deleted';
    }

    protected function createIncludedMetaFactory(): CompletionMetaFactory
    {
        return CompletionMeta::factory()->state(['deleted_on' => now()->subHour()]);
    }

    protected function createExcludedMetaFactory(): CompletionMetaFactory
    {
        return CompletionMeta::factory()->state(['deleted_on' => null]);
    }
}
