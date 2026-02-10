<?php

namespace Tests\Feature\Completions\List;

use App\Models\CompletionMeta;
use Database\Factories\CompletionMetaFactory;

class BlackBorderFilterTest extends ThreeStateFilterTestBase
{
    protected function getFilterName(): string
    {
        return 'black_border';
    }

    protected function createIncludedMetaFactory(): CompletionMetaFactory
    {
        return CompletionMeta::factory()->state(['black_border' => true]);
    }

    protected function createExcludedMetaFactory(): CompletionMetaFactory
    {
        return CompletionMeta::factory()->state(['black_border' => false]);
    }
}
