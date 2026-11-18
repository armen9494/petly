<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_open_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/horizon')
            ->assertOk();
    }

    public function test_other_users_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/horizon')
            ->assertForbidden();
    }

    public function test_guests_are_forbidden(): void
    {
        $this->get('/horizon')->assertForbidden();
    }
}
