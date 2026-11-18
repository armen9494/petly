<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_user_with_the_password_typed_twice(): void
    {
        $this->artisan('app:create-user', ['email' => 'ops@example.com', 'name' => 'Ops'])
            ->expectsQuestion('Password', 'correct-horse')
            ->expectsQuestion('Confirm password', 'correct-horse')
            ->expectsOutputToContain('Created user ops@example.com.')
            ->assertSuccessful();

        $user = User::query()->sole();
        $this->assertSame('ops@example.com', $user->email);
        $this->assertSame('Ops', $user->name);
        $this->assertFalse($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('correct-horse', $user->password));
    }

    public function test_it_creates_an_admin_with_the_admin_option(): void
    {
        $this->artisan('app:create-user', ['email' => 'ops@example.com', 'name' => 'Ops', '--admin' => true])
            ->expectsQuestion('Password', 'correct-horse')
            ->expectsQuestion('Confirm password', 'correct-horse')
            ->expectsOutputToContain('Created admin ops@example.com.')
            ->assertSuccessful();

        $this->assertTrue(User::query()->sole()->is_admin);
    }

    public function test_it_asks_for_a_missing_email_and_name(): void
    {
        $this->artisan('app:create-user')
            ->expectsQuestion("What is the new user's email address?", 'ops@example.com')
            ->expectsQuestion("What is the new user's name?", 'Ops')
            ->expectsQuestion('Password', 'correct-horse')
            ->expectsQuestion('Confirm password', 'correct-horse')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'ops@example.com', 'name' => 'Ops']);
    }

    public function test_it_stores_the_email_in_lowercase(): void
    {
        $this->artisan('app:create-user', ['email' => ' Ops@Example.COM ', 'name' => 'Ops'])
            ->expectsQuestion('Password', 'correct-horse')
            ->expectsQuestion('Confirm password', 'correct-horse')
            ->assertSuccessful();

        $this->assertSame('ops@example.com', User::query()->sole()->email);
    }

    public function test_it_asks_for_both_passwords_again_when_they_do_not_match(): void
    {
        $this->artisan('app:create-user', ['email' => 'ops@example.com', 'name' => 'Ops'])
            ->expectsQuestion('Password', 'correct-horse')
            ->expectsQuestion('Confirm password', 'correct-hrose')
            ->expectsOutputToContain('The passwords do not match.')
            ->expectsQuestion('Password', 'battery-staple')
            ->expectsQuestion('Confirm password', 'battery-staple')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('battery-staple', User::query()->sole()->password));
    }

    /**
     * A terminal asks again after the error; under test, Laravel's prompt
     * fallback stops the command at the first invalid answer instead.
     */
    public function test_it_rejects_a_password_that_is_too_short(): void
    {
        $this->artisan('app:create-user', ['email' => 'ops@example.com', 'name' => 'Ops'])
            ->expectsQuestion('Password', 'short')
            ->expectsOutputToContain('The password field must be at least 8 characters.')
            ->assertFailed();

        $this->assertDatabaseEmpty('users');
    }

    public function test_it_generates_and_shows_a_password_without_interaction(): void
    {
        Str::createRandomStringsUsing(fn (): string => 'generated-password');

        $this->artisan('app:create-user', ['email' => 'ops@example.com', 'name' => 'Ops', '--no-interaction' => true])
            ->expectsOutputToContain('generated-password')
            ->expectsOutputToContain('This password is shown only once.')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('generated-password', User::query()->sole()->password));
    }

    public function test_it_rejects_an_email_another_user_has(): void
    {
        $existingUser = User::factory()->create(['email' => 'ops@example.com']);

        $this->artisan('app:create-user', ['email' => 'OPS@example.com', 'name' => 'Ops'])
            ->expectsOutputToContain('The email has already been taken.')
            ->assertFailed();

        $this->assertSame(1, User::query()->count());
        $this->assertModelExists($existingUser);
    }

    /**
     * @return array<string, array{email: string, name: string, message: string}>
     */
    public static function invalidDetails(): array
    {
        return [
            'malformed email' => [
                'email' => 'not-an-email',
                'name' => 'Ops',
                'message' => 'The email field must be a valid email address.',
            ],
            'email over 255 characters' => [
                'email' => str_repeat('a', 244).'@example.com',
                'name' => 'Ops',
                'message' => 'The email field must not be greater than 255 characters.',
            ],
            'blank name' => [
                'email' => 'ops@example.com',
                'name' => '   ',
                'message' => 'The name field is required.',
            ],
            'name over 255 characters' => [
                'email' => 'ops@example.com',
                'name' => str_repeat('a', 256),
                'message' => 'The name field must not be greater than 255 characters.',
            ],
        ];
    }

    #[DataProvider('invalidDetails')]
    public function test_it_rejects_invalid_details_before_asking_for_a_password(string $email, string $name, string $message): void
    {
        $this->artisan('app:create-user', ['email' => $email, 'name' => $name])
            ->expectsOutputToContain($message)
            ->assertFailed();

        $this->assertDatabaseEmpty('users');
    }
}
