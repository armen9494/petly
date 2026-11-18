<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['GET', '/profile'])]
    #[TestWith(['PATCH', '/profile'])]
    #[TestWith(['PUT', '/profile/password'])]
    public function test_guests_are_redirected_to_login(string $method, string $uri): void
    {
        $this->call($method, $uri)->assertRedirect('/login');
    }

    public function test_the_profile_page_renders_the_signed_in_users_details(): void
    {
        $user = User::factory()->create(['name' => 'Ada', 'email' => 'ada@example.com']);

        $this->actingAs($user)->get('/profile')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('web/Profile')
            ->where('auth.user.name', 'Ada')
            ->where('auth.user.email', 'ada@example.com'));
    }

    public function test_changing_the_email_saves_it_and_clears_its_verification(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')->patch('/profile', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_keeping_the_same_email_saves_the_name_and_keeps_the_email_verified(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->actingAs($user)->from('/profile')->patch('/profile', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_an_email_taken_by_another_user_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'taken@example.com',
        ])->assertSessionHasErrors(['email' => 'The email has already been taken.']);

        $this->assertSame('ada@example.com', $user->refresh()->email);
    }

    public function test_an_empty_profile_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch('/profile', ['name' => '', 'email' => ''])
            ->assertSessionHasErrors([
                'name' => 'The name field is required.',
                'email' => 'The email field is required.',
            ]);
    }

    public function test_users_can_change_their_password_and_stay_logged_in(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')->put('/profile/password', [
            'current_password' => 'password',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertTrue(Hash::check('correct-horse-battery', $user->refresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'wrong',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertSessionHasErrors(['current_password' => 'Your current password is incorrect.']);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_a_new_password_that_does_not_match_its_confirmation_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'password',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'something-else',
        ])->assertSessionHasErrors(['password' => 'The password field confirmation does not match.']);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_password_changes_are_limited_to_six_attempts_a_minute(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $attempt = ['current_password' => 'wrong', 'password' => 'correct-horse-battery', 'password_confirmation' => 'correct-horse-battery'];

        foreach (range(1, 6) as $ignored) {
            $this->actingAs($user)->put('/profile/password', $attempt)->assertSessionHasErrors('current_password');
        }

        $this->actingAs($user)->put('/profile/password', $attempt)->assertTooManyRequests();
    }
}
