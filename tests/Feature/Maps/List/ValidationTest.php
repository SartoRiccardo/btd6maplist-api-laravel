<?php

namespace Tests\Feature\Maps\List;

use Tests\TestCase;

class ValidationTest extends TestCase
{
    #[Group('get')]
    #[Group('maps')]
    #[Group('validation')]
    public function test_invalid_format_id_returns_validation_error(): void
    {
        $this->getJson('/api/maps?format_id=99999')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['format_id']);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('validation')]
    public function test_invalid_deleted_status_returns_validation_error(): void
    {
        $this->getJson('/api/maps?deleted=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['deleted']);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('validation')]
    public function test_negative_page_returns_validation_error(): void
    {
        $this->getJson('/api/maps?page=-1')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }

    #[Group('get')]
    #[Group('maps')]
    #[Group('validation')]
    public function test_zero_per_page_returns_validation_error(): void
    {
        $this->getJson('/api/maps?per_page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }
}
