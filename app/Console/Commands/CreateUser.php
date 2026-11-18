<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

/**
 * Creates an account from the command line, such as the first admin on a new
 * server. Run interactively, it asks for the password twice. Run without
 * interaction, as Laravel Cloud's commands are, it generates a password and
 * shows it once, so no password has to be typed into the command itself.
 */
#[Signature('app:create-user
    {email : The new user\'s email address}
    {name : The new user\'s name}
    {--admin : Give the user access to the admin panel}')]
#[Description('Create a user account, optionally with admin access')]
class CreateUser extends Command implements PromptsForMissingInput
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = trim((string) $this->argument('name'));

        $validator = Validator::make(['email' => $email, 'name' => $name], [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $generatedPassword = $this->input->isInteractive() ? null : Str::random(32);

        $user = User::query()->forceCreate([
            'name' => $name,
            'email' => $email,
            'password' => $generatedPassword ?? $this->askForPassword(),
            'is_admin' => (bool) $this->option('admin'),
            'email_verified_at' => now(),
        ]);

        $this->components->info($user->is_admin ? "Created admin {$user->email}." : "Created user {$user->email}.");

        if ($generatedPassword !== null) {
            $this->components->twoColumnDetail('Password', $generatedPassword);
            $this->components->warn('This password is shown only once. Change it from the profile page after signing in.');
        }

        return self::SUCCESS;
    }

    /**
     * Ask for the password and its confirmation, starting over when they differ.
     */
    private function askForPassword(): string
    {
        while (true) {
            $password = password(label: 'Password', required: true, validate: ['password' => Password::defaults()]);

            if (password(label: 'Confirm password', required: true) === $password) {
                return $password;
            }

            $this->components->error('The passwords do not match.');
        }
    }
}
