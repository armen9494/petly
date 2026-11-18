<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:make-admin {email : The email address of an existing user}')]
#[Description('Give a user access to the admin panel')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->components->error('No user has that email address.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();

        $this->components->info("{$user->email} is now an admin.");

        return self::SUCCESS;
    }
}
