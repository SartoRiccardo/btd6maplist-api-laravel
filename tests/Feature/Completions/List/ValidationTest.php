<?php

namespace Tests\Feature\Completions\List;

use Tests\TestCase;

class ValidationTest extends TestCase
{
    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_timestamp_must_be_integer(): void
    {
        $this->getJson('/api/completions?timestamp=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors('timestamp');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_timestamp_must_be_positive_or_zero(): void
    {
        $this->getJson('/api/completions?timestamp=-1')
            ->assertStatus(422)
            ->assertJsonValidationErrors('timestamp');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_page_must_be_integer(): void
    {
        $this->getJson('/api/completions?page=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors('page');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_page_must_be_at_least_1(): void
    {
        $this->getJson('/api/completions?page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors('page');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_per_page_must_be_integer(): void
    {
        $this->getJson('/api/completions?per_page=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_per_page_must_be_at_least_1(): void
    {
        $this->getJson('/api/completions?per_page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_per_page_must_not_exceed_maximum(): void
    {
        $this->getJson('/api/completions?per_page=151')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_player_id_must_exist(): void
    {
        $this->getJson('/api/completions?player_id=999999999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('player_id');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_map_code_must_exist(): void
    {
        $this->getJson('/api/completions?map_code=NONEXISTENT')
            ->assertStatus(422)
            ->assertJsonValidationErrors('map_code');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_deleted_must_be_valid_value(): void
    {
        $this->getJson('/api/completions?deleted=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors('deleted');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_pending_must_be_valid_value(): void
    {
        $this->getJson('/api/completions?pending=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors('pending');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_no_geraldo_must_be_valid_value(): void
    {
        $this->getJson('/api/completions?no_geraldo=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors('no_geraldo');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_lcc_must_be_valid_value(): void
    {
        $this->getJson('/api/completions?lcc=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors('lcc');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_black_border_must_be_valid_value(): void
    {
        $this->getJson('/api/completions?black_border=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors('black_border');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_sort_by_must_be_valid_value(): void
    {
        $this->getJson('/api/completions?sort_by=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort_by');
    }

    #[Group('get')]
    #[Group('completions')]
    #[Group('validation')]
    public function test_sort_order_must_be_valid_value(): void
    {
        $this->getJson('/api/completions?sort_order=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort_order');
    }
}
