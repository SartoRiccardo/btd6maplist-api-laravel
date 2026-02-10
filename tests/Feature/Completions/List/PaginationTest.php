<?php

namespace Tests\Feature\Completions\List;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\User;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    #[Group('get')]
    #[Group('completions')]
    #[Group('pagination')]
    public function test_returns_completions_with_custom_pagination(): void
    {
        $total = 25;
        $page = 2;
        $perPage = 10;

        $completions = Completion::factory()->count($total)->create();
        $player = User::factory()->create();

        $metas = CompletionMeta::factory()
            ->count($total)
            ->sequence(fn($seq) => [
                'completion_id' => $completions[$seq->index]->id,
                'created_on' => now()->subSeconds(100 - $seq->index),
            ])
            ->create();

        // Attach player to each meta
        foreach ($metas as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        // Get the correct page of completions based on created_on order
        $completionIds = $metas->sortBy('created_on')->pluck('completion_id')->forPage($page, $perPage);
        $pageCompletions = $completions->whereIn('id', $completionIds)->sortBy(fn($c) => $completionIds->search($c->id))->values();

        $metasByKey = $metas->keyBy('completion_id');
        $pageMetas = $pageCompletions->map(fn($completion) => $metasByKey->get($completion->id))->values();
        $pageMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));

        $expected = CompletionTestHelper::expectedCompletionLists($pageCompletions, $pageMetas, [
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
            'per_page' => $perPage,
            'total' => $total,
        ]);

        $actual = $this->getJson("/api/completions?page={$page}&per_page={$perPage}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('pagination')]
    public function test_returns_empty_array_on_page_overflow(): void
    {
        $count = 5;
        $completions = Completion::factory()->count($count)->create();
        $player = User::factory()->create();

        $metas = CompletionMeta::factory()
            ->count($count)
            ->sequence(fn($seq) => [
                'completion_id' => $completions[$seq->index]->id,
                'created_on' => now()->subHour(),
            ])
            ->create();

        foreach ($metas as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        $expected = [
            'data' => [],
            'meta' => [
                'current_page' => 999,
                'last_page' => 1,
                'per_page' => 100,
                'total' => $count,
            ],
        ];

        $actual = $this->getJson('/api/completions?page=999')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('pagination')]
    public function test_caps_per_page_at_maximum(): void
    {
        $this->getJson('/api/completions?per_page=151')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('per_page');
    }
}
