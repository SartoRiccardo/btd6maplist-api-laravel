<?php

namespace Tests\Feature\Completions;

use App\Models\User;
use PHPUnit\Attributes\Group;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;

class GetRecentCompletionsTest extends TestCase
{
    #[Group('get')]
    public function test_get_recent_completions_returns_valid_structure(): void
    {
        $user = User::factory()->create();
        $completions = CompletionTestHelper::createCompletionsWithMeta(10, $user, accepted: true);

        $actual = $this->getJson('/api/completions/recent')
            ->assertStatus(200)
            ->json();

        $expected = $completions
            ->sortByDesc('submitted_on')
            ->take(5)
            ->map(function ($completion) use ($user) {
                $completion->load(['map.latestMeta', 'latestMeta', 'proofs']);
                return CompletionTestHelper::expectedCompletionResponse($completion->latestMeta, $completion, $user, false);
            })->values()->toArray();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    public function test_get_recent_completions_default_formats(): void
    {
        $user = User::factory()->create();

        CompletionTestHelper::createCompletionsWithMeta(2, $user, accepted: true, formatId: 1);
        CompletionTestHelper::createCompletionsWithMeta(1, $user, accepted: true, formatId: 51);
        CompletionTestHelper::createCompletionsWithMeta(1, $user, accepted: true, formatId: 2);

        $actual = $this->getJson('/api/completions/recent')
            ->assertStatus(200)
            ->json();

        $expectedFormats = [1, 1, 51];
        $actualFormats = array_column($actual, 'format');
        sort($actualFormats);

        $this->assertEquals($expectedFormats, $actualFormats);
    }

    #[Group('get')]
    public function test_get_recent_completions_format_filtering(): void
    {
        $user = User::factory()->create();

        CompletionTestHelper::createCompletionsWithMeta(3, $user, accepted: true, formatId: 1);
        CompletionTestHelper::createCompletionsWithMeta(2, $user, accepted: true, formatId: 51);

        $actual = $this->getJson('/api/completions/recent?formats=1')
            ->assertStatus(200)
            ->json();

        $expectedFormats = [1, 1, 1];
        $actualFormats = array_column($actual, 'format');
        sort($actualFormats);

        $this->assertEquals($expectedFormats, $actualFormats);
    }

    #[Group('get')]
    public function test_get_recent_completions_multiple_formats(): void
    {
        $user = User::factory()->create();

        CompletionTestHelper::createCompletionsWithMeta(2, $user, accepted: true, formatId: 1);
        CompletionTestHelper::createCompletionsWithMeta(2, $user, accepted: true, formatId: 51);
        CompletionTestHelper::createCompletionsWithMeta(1, $user, accepted: true, formatId: 2);

        $actual = $this->getJson('/api/completions/recent?formats=1,51,2')
            ->assertStatus(200)
            ->json();

        $expectedFormats = [1, 1, 2, 51, 51];
        $actualFormats = array_column($actual, 'format');
        sort($actualFormats);

        $this->assertEquals($expectedFormats, $actualFormats);
    }

    #[Group('get')]
    public function test_get_recent_completions_only_returns_accepted(): void
    {
        $user = User::factory()->create();

        $accepted = CompletionTestHelper::createCompletionsWithMeta(3, $user, accepted: true, formatId: 1);
        CompletionTestHelper::createCompletionsWithMeta(2, $user, accepted: false, formatId: 1);

        $actual = $this->getJson('/api/completions/recent')
            ->assertStatus(200)
            ->json();

        $expectedIds = $accepted->pluck('id')->sort()->values()->toArray();
        $actualIds = array_column($actual, 'id');
        sort($actualIds);

        $this->assertEquals($expectedIds, $actualIds);
    }

    #[Group('get')]
    public function test_get_recent_completions_excludes_deleted(): void
    {
        $user = User::factory()->create();

        CompletionTestHelper::createCompletionsWithMeta(2, $user, deleted: true, formatId: 1);
        $accepted = CompletionTestHelper::createCompletionsWithMeta(3, $user, accepted: true, formatId: 1);

        $actual = $this->getJson('/api/completions/recent')
            ->assertStatus(200)
            ->json();

        $expectedIds = $accepted->pluck('id')->sort()->values()->toArray();
        $actualIds = array_column($actual, 'id');
        sort($actualIds);

        $this->assertEquals($expectedIds, $actualIds);
    }
}
