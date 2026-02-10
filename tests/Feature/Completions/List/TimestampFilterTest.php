<?php

namespace Tests\Feature\Completions\List;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\User;
use Tests\Helpers\CompletionTestHelper;
use Tests\TestCase;

class TimestampFilterTest extends TestCase
{
    #[Group('get')]
    #[Group('completions')]
    #[Group('timestamp')]
    public function test_default_timestamp_returns_active_completions(): void
    {
        $now = now();

        $includedCompletions = Completion::factory()->count(3)->create();
        $player = User::factory()->create();

        $includedMetas = CompletionMeta::factory()
            ->count(3)
            ->sequence(fn($seq) => [
                'completion_id' => $includedCompletions[$seq->index]->id,
                'created_on' => $now->copy()->subHour()->addSeconds($seq->index),
                'deleted_on' => null,
            ])
            ->create();

        foreach ($includedMetas as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        // Obsolete versions
        CompletionMeta::factory()
            ->for($includedCompletions[0])
            ->create([
                'completion_id' => $includedCompletions[0]->id,
                'created_on' => $now->copy()->subHours(2),
                'deleted_on' => null,
            ]);

        // Future versions
        Completion::factory()->count(2)->withMeta([
            'created_on' => $now->addHour(),
        ])->create();

        // Active but deleted previously
        Completion::factory()->count(2)->withMeta([
            'created_on' => $now->subHour(),
            'deleted_on' => $now->subMinute(),
        ])->create();

        $includedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $expected = CompletionTestHelper::expectedCompletionLists($includedCompletions, $includedMetas);

        $actual = $this->getJson('/api/completions')
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('timestamp')]
    public function test_historical_timestamp_returns_completions_active_at_that_time(): void
    {
        $timestamp = now()->subHours(2);

        $includedCompletions = Completion::factory()->count(3)->create();
        $player = User::factory()->create();

        $includedMetas = CompletionMeta::factory()
            ->count(3)
            ->sequence(fn($seq) => [
                'completion_id' => $includedCompletions[$seq->index]->id,
                'created_on' => $timestamp->copy()->subHour()->addSeconds($seq->index),
                'deleted_on' => null,
            ])
            ->create();

        foreach ($includedMetas as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        Completion::factory()->count(2)->withMeta([
            'created_on' => $timestamp->copy()->addHour(),
        ])->create();

        Completion::factory()->count(2)->withMeta([
            'created_on' => $timestamp->copy()->subHours(2),
            'deleted_on' => $timestamp->copy()->subHour(),
        ])->create();

        $includedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $expected = CompletionTestHelper::expectedCompletionLists($includedCompletions, $includedMetas);

        $actual = $this->getJson('/api/completions?timestamp=' . $timestamp->timestamp)
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('timestamp')]
    public function test_future_timestamp_returns_non_deleted_completions(): void
    {
        $future = now()->addHours(2);

        $includedCompletions = Completion::factory()->count(5)->create();
        $player = User::factory()->create();

        $includedMetas = CompletionMeta::factory()
            ->count($includedCompletions->count())
            ->sequence(fn($seq) => [
                'completion_id' => $includedCompletions[$seq->index]->id,
                'created_on' => now()->subHour()->addSeconds($seq->index),
                'deleted_on' => $seq->index < 3 ? null : $future->copy()->addHour(),
            ])
            ->create();

        foreach ($includedMetas as $meta) {
            $meta->players()->attach($player, ['run' => $meta->id]);
        }

        Completion::factory()->withMeta([
            'created_on' => now()->subHour(),
            'deleted_on' => $future->copy()->subHour(),
        ])->create();

        $includedMetas->each(fn($meta) => $meta->load(['players', 'completion.map']));
        $expected = CompletionTestHelper::expectedCompletionLists($includedCompletions, $includedMetas);

        $actual = $this->getJson('/api/completions?timestamp=' . $future->timestamp)
            ->assertStatus(200)
            ->json();

        $this->assertEquals($expected, $actual);
    }
}
