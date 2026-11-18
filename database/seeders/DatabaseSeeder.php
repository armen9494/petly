<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\CreditLedger;
use App\Support\Money;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The last field of a DEMO_USERS entry that makes the account an admin.
     */
    private const string ADMIN_ROLE = 'admin';

    /**
     * Seed the demo accounts listed in DEMO_USERS (see config/demo.php). Safe to
     * re-run: accounts that already exist keep their credits and only have their
     * password reset to DEMO_USERS_PASSWORD.
     */
    public function run(): void
    {
        $accounts = $this->demoAccounts();

        if ($accounts === []) {
            $this->command?->warn('DEMO_USERS is empty, so no demo accounts were seeded.');

            return;
        }

        $password = (string) config('demo.password');

        if ($password === '') {
            throw new InvalidArgumentException('Set DEMO_USERS_PASSWORD to seed the accounts in DEMO_USERS.');
        }

        foreach ($accounts as $account) {
            $this->seedAccount($password, ...$account);
        }
    }

    /**
     * @return list<array{email: string, name: string, creditsInDollars: int, isAdmin: bool}>
     */
    private function demoAccounts(): array
    {
        return Str::of((string) config('demo.users'))
            ->explode(',')
            ->map(fn (string $entry): string => trim($entry))
            ->filter(fn (string $entry): bool => $entry !== '')
            ->map($this->parseAccount(...))
            ->values()
            ->all();
    }

    /**
     * Parse one "email:name:credits" entry, optionally followed by ":admin".
     *
     * @return array{email: string, name: string, creditsInDollars: int, isAdmin: bool}
     */
    private function parseAccount(string $entry): array
    {
        $fields = array_map(trim(...), explode(':', $entry));
        [$email, $name, $credits, $role] = array_pad($fields, 4, '');

        $isValid = count($fields) <= 4
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && $name !== ''
            && ctype_digit($credits)
            && in_array($role, ['', self::ADMIN_ROLE], true);

        if (! $isValid) {
            throw new InvalidArgumentException(
                "Invalid DEMO_USERS entry \"{$entry}\": use email:name:credits, with :admin appended for admins."
            );
        }

        return [
            'email' => Str::lower($email),
            'name' => $name,
            'creditsInDollars' => (int) $credits,
            'isAdmin' => $role === self::ADMIN_ROLE,
        ];
    }

    /**
     * Create the account, or reset an existing one's password. It doesn't use the
     * user factory because Faker is a dev dependency and this also runs on servers.
     */
    private function seedAccount(string $password, string $email, string $name, int $creditsInDollars, bool $isAdmin): void
    {
        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser !== null) {
            $existingUser->update(['password' => $password]);

            return;
        }

        $user = User::query()->forceCreate([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_admin' => $isAdmin,
            'email_verified_at' => now(),
        ]);

        if ($creditsInDollars > 0) {
            app(CreditLedger::class)->purchase($user, Money::fromDollars($creditsInDollars));
        }
    }
}
