<?php

namespace Tests\Feature\Completions\List;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\User;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;

class SortTest extends TestCase
{
    private User $player;
    private $now;
    private $completions;
    private $metas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = User::factory()->create();
        $this->now = now();

        $this->completions = Completion::factory()->count(3)->create();
        $this->metas = CompletionMeta::factory()
            ->count(3)
            ->sequence(fn($seq) => [
                'completion_id' => $this->completions[$seq->index]->id,
                'created_on' => $this->now->copy()->subSeconds(10 - $seq->index),
            ])
            ->create();

        foreach ($this->metas as $meta) {
            $meta->players()->attach($this->player, ['run' => $meta->id]);
        }
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('sort')]
    public function test_default_sorts_by_created_on_asc(): void
    {
        // Order by created_on ascending
        $sortedMetas = $this->metas->sortBy('created_on')->values();
        $sortedCompletions = $this->completions->sortBy(fn($c) => $sortedMetas->search($sortedMetas->firstWhere('completion_id', $c->id)))->values();

        $sortedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $expected = CompletionTestHelper::expectedCompletionLists($sortedCompletions, $sortedMetas);

        $actual = $this->getJson('/api/completions')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('sort')]
    public function test_sort_order_desc_works(): void
    {
        // Order by created_on descending
        $sortedMetas = $this->metas->sortByDesc('created_on')->values();
        $sortedCompletions = $this->completions->sortBy(fn($c) => $sortedMetas->search($sortedMetas->firstWhere('completion_id', $c->id)))->values();

        $sortedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));

        $expected = CompletionTestHelper::expectedCompletionLists($sortedCompletions, $sortedMetas);

        $actual = $this->getJson('/api/completions?sort_order=desc')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }
}
