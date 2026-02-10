<?php

namespace Tests\Feature\Completions\List;

use App\Models\CompletionMeta;
use Database\Factories\CompletionMetaFactory;

class NoGeraldoFilterTest extends ThreeStateFilterTestBase
{
    protected function getFilterName(): string
    {
        return 'no_geraldo';
    }

    protected function createIncludedMetaFactory(): CompletionMetaFactory
    {
        return CompletionMeta::factory()->state(['no_geraldo' => true]);
    }

    protected function createExcludedMetaFactory(): CompletionMetaFactory
    {
        return CompletionMeta::factory()->state(['no_geraldo' => false]);
    }
}
