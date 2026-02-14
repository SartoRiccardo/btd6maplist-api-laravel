<?php

namespace Tests\Feature\Completions\List;

use App\Models\Completion;
use App\Models\User;
use Database\Factories\CompletionMetaFactory;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;

abstract class ThreeStateFilterTestBase extends TestCase
{
    protected User $player;

    abstract protected function getFilterName(): string;

    abstract protected function createIncludedMetaFactory(): CompletionMetaFactory;

    abstract protected function createExcludedMetaFactory(): CompletionMetaFactory;

    /**
     * Whether metas from createIncludedMetaFactory should have is_current_lcc=true.
     * Override in subclasses that use LCCs.
     */
    protected function includedHasCurrentLcc(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->player = User::factory()->create();
    }

    /**
     * Get the "any" value that returns all results (no filtering applied).
     * Most filters use 'any', but some like deleted/pending use different defaults.
     */
    protected function getAnyValue(): string
    {
        return 'any';
    }

    /**
     * Create metas using the provided factory and sequence callback,
     * then attach the player to each meta.
     */
    protected function createMetasWithPlayer(
        CompletionMetaFactory $factory,
        $completions,
        callable $sequenceFn
    ) {
        $metas = $factory
            ->count(count($completions))
            ->sequence(fn($seq) => [
                'completion_id' => $completions[$seq->index]->id,
                ...$sequenceFn($seq),
            ])
            ->create();

        foreach ($metas as $meta) {
            $meta->players()->attach($this->player, ['run' => $meta->id]);
        }

        return $metas;
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('filter')]
    public function test_only_returns_filtered_items_only(): void
    {
        $filterName = $this->getFilterName();

        $includedCompletions = Completion::factory()->count(3)->create();
        $includedMetas = $this->createMetasWithPlayer(
            $this->createIncludedMetaFactory(),
            $includedCompletions,
            fn($seq) => ['created_on' => now()->subSeconds(10 - $seq->index)]
        );

        // Excluded: items without the filter condition
        $excludedAttrs = $this->createExcludedMetaFactory()->raw();
        Completion::factory()->count(2)->withMeta($excludedAttrs)->create();

        $includedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $overrides = $this->includedHasCurrentLcc() ? ['is_current_lcc' => true] : [];
        $expected = CompletionTestHelper::expectedCompletionListsWithOverrides($includedCompletions, $includedMetas, $overrides);

        $actual = $this->getJson("/api/completions?{$filterName}=only")
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('filter')]
    public function test_exclude_filters_out_items(): void
    {
        $filterName = $this->getFilterName();

        $includedCompletions = Completion::factory()->count(2)->create();
        $includedMetas = $this->createMetasWithPlayer(
            $this->createExcludedMetaFactory(),
            $includedCompletions,
            fn($seq) => ['created_on' => now()->subHours(2)->addSeconds($seq->index)]
        );

        // Excluded: items with the filter condition
        $excludedAttrs = $this->createIncludedMetaFactory()->raw();
        Completion::factory()->count(3)->withMeta($excludedAttrs)->create();

        $includedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));

        $metasByKey = $includedMetas->keyBy('completion_id');

        $data = $includedCompletions
            ->map(fn($c) => CompletionTestHelper::mergeCompletionMeta($c, $metasByKey->get($c->id)))
            ->values()
            ->toArray();

        $expected = [
            'data' => $data,
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => 2,
            ],
        ];

        $actual = $this->getJson("/api/completions?{$filterName}=exclude")
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('filter')]
    public function test_any_returns_all_items(): void
    {
        $filterName = $this->getFilterName();
        $anyValue = $this->getAnyValue();

        $completions1 = Completion::factory()->count(2)->create();
        $metas1 = $this->createMetasWithPlayer(
            $this->createIncludedMetaFactory(),
            $completions1,
            fn($seq) => ['created_on' => now()->subSeconds(10 - $seq->index)]
        );

        $completions2 = Completion::factory()->count(3)->create();
        $metas2 = $this->createMetasWithPlayer(
            $this->createExcludedMetaFactory(),
            $completions2,
            fn($seq) => ['created_on' => now()->subSeconds(20 - $seq->index)]
        );

        $allMetas = $metas1->concat($metas2)->sortBy('created_on')->values();
        $allCompletions = $completions1->concat($completions2);

        $allMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));

        $completionsByKey = $allCompletions->keyBy('id');

        $includedOverrides = $this->includedHasCurrentLcc() ? ['is_current_lcc' => true] : [];
        $excludedOverrides = [];

        $data = $allMetas
            ->map(fn($meta) => CompletionTestHelper::mergeCompletionMeta(
                $completionsByKey->get($meta->completion_id),
                $meta,
                in_array($meta, $metas1->all()) ? $includedOverrides : $excludedOverrides
            ))
            ->values()
            ->toArray();

        $expected = [
            'data' => $data,
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 100,
                'total' => 5,
            ],
        ];

        $actual = $this->getJson("/api/completions?{$filterName}={$anyValue}")
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }
}
