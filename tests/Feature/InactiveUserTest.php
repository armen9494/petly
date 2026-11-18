<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InactiveUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deactivated_user_is_signed_out_on_their_next_request(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->get('/')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'This account is inactive.']);

        $this->assertGuest();
    }

    public function test_the_login_page_tells_a_signed_out_user_why(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/credits')->assertOk();
        $user->forceFill(['is_active' => false])->save();

        $this->followingRedirects()->get('/credits')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/Login')
            ->where('errors.email', 'This account is inactive.'));
    }

    public function test_a_deactivated_users_remember_me_cookie_no_longer_signs_them_in(): void
    {
        $user = User::factory()->create();
        $guard = Auth::guard();
        $rememberCookie = $user->id.'|'.$user->getRememberToken().'|'.$guard->hashPasswordForCookie($user->getAuthPassword());
        $user->forceFill(['is_active' => false])->save();

        $this->withCookie($guard->getRecallerName(), $rememberCookie)->get('/profile')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'This account is inactive.']);

        $this->assertGuest();
        $this->assertNotSame($user->getRememberToken(), $user->refresh()->getRememberToken());
    }

    public function test_an_inactive_user_with_the_right_password_is_told_the_account_is_inactive(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This account is inactive.']);

        $this->assertGuest();
    }

    public function test_an_inactive_user_with_a_wrong_password_gets_the_usual_error(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Those credentials do not match our records.']);

        $this->assertGuest();
    }
}
