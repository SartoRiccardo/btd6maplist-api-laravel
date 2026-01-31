<?php

namespace Database\Factories;

use App\Models\CompletionMeta;
use App\Models\CompletionProof;
use App\Models\Completion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompletionProof>
 */
class CompletionProofFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run' => CompletionMeta::factory(),
            'proof_url' => fake()->url(),
            'proof_type' => fake()->randomElement([0, 1]), // 0 = image, 1 = video
        ];
    }

    /**
     * Indicate the proof is an image.
     */
    public function image(): static
    {
        return $this->state(fn(array $attributes) => [
            'proof_type' => 0,
            'proof_url' => 'https://dummyimage.com/' . fake()->randomNumber(3, true) . 'x' . fake()->randomNumber(3, true),
        ]);
    }

    /**
     * Indicate the proof is a video.
     */
    public function video(): static
    {
        return $this->state(fn(array $attributes) => [
            'proof_type' => 1,
            'proof_url' => 'https://youtu.be/' . fake()->regexify('[a-zA-Z0-9_-]{11}'),
        ]);
    }

}
