<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_gives_an_existing_user_admin_access(): void
    {
        $user = User::factory()->create(['email' => 'ops@example.com']);

        $this->artisan('app:make-admin', ['email' => 'ops@example.com'])
            ->expectsOutputToContain('ops@example.com is now an admin.')
            ->assertSuccessful();

        $this->assertTrue($user->refresh()->is_admin);
    }

    public function test_it_fails_for_an_email_no_user_has(): void
    {
        $user = User::factory()->create();

        $this->artisan('app:make-admin', ['email' => 'nobody@example.com'])
            ->expectsOutputToContain('No user has that email address.')
            ->assertFailed();

        $this->assertFalse($user->refresh()->is_admin);
    }
}
