<?php

namespace Tests\Feature\Completions;

use App\Models\User;
use PHPUnit\Attributes\Group;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;

class GetUnapprovedCompletionsTest extends TestCase
{
    #[Group('get')]
    public function test_get_unapproved_completions_returns_valid_structure(): void
    {
        $user = User::factory()->create();
        $completions = CompletionTestHelper::createCompletionsWithMeta(10, $user, accepted: false);

        $actual = $this->getJson('/api/completions/unapproved')
            ->assertStatus(200)
            ->json();

        $expected = $completions
            ->sortByDesc('submitted_on')
            ->take(10)
            ->map(function ($completion) use ($user) {
                $completion->load(['map.latestMeta', 'latestMeta', 'proofs']);
                return CompletionTestHelper::expectedCompletionResponse($completion->latestMeta, $completion, $user, false);
            })->values()->toArray();

        $this->assertEquals([
            'completions' => $expected,
            'total' => 10,
            'pages' => 1,
        ], $actual);
    }

    #[Group('get')]
    public function test_get_unapproved_completions_only_returns_pending(): void
    {
        $user = User::factory()->create();

        $pending = CompletionTestHelper::createCompletionsWithMeta(3, $user, accepted: false);
        CompletionTestHelper::createCompletionsWithMeta(2, $user, accepted: true);

        $actual = $this->getJson('/api/completions/unapproved')
            ->assertStatus(200)
            ->json();

        $expectedIds = $pending->pluck('id')->sort()->values()->toArray();
        $actualIds = array_column($actual['completions'], 'id');
        sort($actualIds);

        $this->assertEquals($expectedIds, $actualIds);
    }

    #[Group('get')]
    public function test_get_unapproved_completions_excludes_deleted(): void
    {
        $user = User::factory()->create();

        CompletionTestHelper::createCompletionsWithMeta(2, $user, deleted: true);
        $pending = CompletionTestHelper::createCompletionsWithMeta(3, $user, accepted: false);

        $actual = $this->getJson('/api/completions/unapproved')
            ->assertStatus(200)
            ->json();

        $expectedIds = $pending->pluck('id')->sort()->values()->toArray();
        $actualIds = array_column($actual['completions'], 'id');
        sort($actualIds);

        $this->assertEquals($expectedIds, $actualIds);
    }

    #[Group('get')]
    public function test_get_unapproved_completions_returns_all_formats(): void
    {
        $user = User::factory()->create();

        CompletionTestHelper::createCompletionsWithMeta(2, $user, accepted: false, formatId: 1);
        CompletionTestHelper::createCompletionsWithMeta(2, $user, accepted: false, formatId: 51);
        CompletionTestHelper::createCompletionsWithMeta(1, $user, accepted: false, formatId: 2);

        $actual = $this->getJson('/api/completions/unapproved')
            ->assertStatus(200)
            ->json();

        $expectedFormats = [1, 1, 2, 51, 51];
        $actualFormats = array_column($actual['completions'], 'format');
        sort($actualFormats);

        $this->assertEquals($expectedFormats, $actualFormats);
    }

    #[Group('get')]
    public function test_get_unapproved_completions_pagination(): void
    {
        $user = User::factory()->create();

        // Create 10 completions to test pagination with per_page=3
        CompletionTestHelper::createCompletionsWithMeta(10, $user, accepted: false);

        // First page
        $page1 = $this->getJson('/api/completions/unapproved?page=1&per_page=3')
            ->assertStatus(200)
            ->json();

        // Verify pagination metadata
        $this->assertCount(3, $page1['completions']);
        $this->assertEquals(10, $page1['total']);
        $this->assertEquals(4, $page1['pages']);

        // Get second page
        $page2 = $this->getJson('/api/completions/unapproved?page=2&per_page=3')
            ->assertStatus(200)
            ->json();

        // Verify different completions
        $page1Ids = array_column($page1['completions'], 'id');
        $page2Ids = array_column($page2['completions'], 'id');
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
    }

    #[Group('get')]
    public function test_get_unapproved_completions_format_filtering(): void
    {
        $user = User::factory()->create();

        CompletionTestHelper::createCompletionsWithMeta(3, $user, accepted: false, formatId: 1);
        CompletionTestHelper::createCompletionsWithMeta(2, $user, accepted: false, formatId: 51);

        $actual = $this->getJson('/api/completions/unapproved?formats=1')
            ->assertStatus(200)
            ->json();

        $expectedFormats = [1, 1, 1];
        $actualFormats = array_column($actual['completions'], 'format');
        sort($actualFormats);

        $this->assertEquals($expectedFormats, $actualFormats);
    }

    #[Group('get')]
    public function test_get_unapproved_completions_multiple_formats(): void
    {
        $user = User::factory()->create();

        CompletionTestHelper::createCompletionsWithMeta(2, $user, accepted: false, formatId: 1);
        CompletionTestHelper::createCompletionsWithMeta(2, $user, accepted: false, formatId: 51);
        CompletionTestHelper::createCompletionsWithMeta(1, $user, accepted: false, formatId: 2);

        $actual = $this->getJson('/api/completions/unapproved?formats=1,51,2')
            ->assertStatus(200)
            ->json();

        $expectedFormats = [1, 1, 2, 51, 51];
        $actualFormats = array_column($actual['completions'], 'format');
        sort($actualFormats);

        $this->assertEquals($expectedFormats, $actualFormats);
    }

    #[Group('get')]
    public function test_get_unapproved_completions_ordered_by_submitted_on(): void
    {
        $user = User::factory()->create();

        CompletionTestHelper::createCompletionsWithMeta(5, $user, accepted: false);

        $actual = $this->getJson('/api/completions/unapproved')
            ->assertStatus(200)
            ->json();

        if (count($actual['completions']) > 1) {
            $submittedOn = array_column($actual['completions'], 'submitted_on');
            $sorted = $submittedOn;
            rsort($sorted);
            $this->assertEquals($sorted, $submittedOn);
        }
    }
}
