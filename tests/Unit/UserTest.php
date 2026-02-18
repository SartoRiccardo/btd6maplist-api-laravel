<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_user_to_array_serializes_discord_id_as_string(): void
    {
        $user = User::factory()->create(['discord_id' => '123456789012345678']);

        $array = $user->toArray();

        $this->assertIsString($array['discord_id']);
        $this->assertEquals('123456789012345678', $array['discord_id']);
    }
}
