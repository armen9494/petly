<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CreditLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_listed_accounts_with_their_name_role_credits_and_password(): void
    {
        config([
            'demo.users' => 'Ops@Example.com:Olivia Ops:10:admin, viewer@example.com:Victor View:0',
            'demo.password' => 'reviewer-pass',
        ]);

        $this->seed();

        $admin = User::query()->where('email', 'ops@example.com')->sole();
        $this->assertSame('Olivia Ops', $admin->name);
        $this->assertTrue($admin->is_admin);
        $this->assertTrue(Hash::check('reviewer-pass', $admin->password));
        $this->assertSame(10_000_000_000, app(CreditLedger::class)->balance($admin));

        $viewer = User::query()->where('email', 'viewer@example.com')->sole();
        $this->assertSame('Victor View', $viewer->name);
        $this->assertFalse($viewer->is_admin);
        $this->assertTrue(Hash::check('reviewer-pass', $viewer->password));
        $this->assertSame(0, $viewer->creditTransactions()->count());
    }

    public function test_re_seeding_resets_the_password_and_keeps_the_original_credits(): void
    {
        config(['demo.users' => 'ops@example.com:Olivia Ops:10', 'demo.password' => 'first-pass']);
        $this->seed();
        config(['demo.users' => 'ops@example.com:Olivia Ops:50', 'demo.password' => 'second-pass']);

        $this->seed();

        $user = User::query()->where('email', 'ops@example.com')->sole();
        $this->assertTrue(Hash::check('second-pass', $user->password));
        $this->assertSame(10_000_000_000, app(CreditLedger::class)->balance($user));
        $this->assertSame(1, $user->creditTransactions()->count());
    }

    public function test_creates_no_accounts_when_none_are_listed(): void
    {
        config(['demo.users' => '', 'demo.password' => null]);

        $this->seed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_rejects_listed_accounts_without_a_password(): void
    {
        config(['demo.users' => 'ops@example.com:Olivia Ops:10', 'demo.password' => null]);

        $this->assertThrows(fn () => $this->seed(), InvalidArgumentException::class, 'Set DEMO_USERS_PASSWORD');

        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('malformedEntries')]
    public function test_rejects_a_malformed_entry_before_creating_any_account(string $entry): void
    {
        config(['demo.users' => "ops@example.com:Olivia Ops:10,{$entry}", 'demo.password' => 'reviewer-pass']);

        $this->assertThrows(
            fn () => $this->seed(),
            InvalidArgumentException::class,
            "Invalid DEMO_USERS entry \"{$entry}\"",
        );

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedEntries(): array
    {
        return [
            'invalid email' => ['not-an-email:Nobody:5'],
            'missing name' => ['nobody@example.com::5'],
            'missing credits' => ['nobody@example.com:Nobody'],
            'fractional credits' => ['nobody@example.com:Nobody:2.50'],
            'negative credits' => ['nobody@example.com:Nobody:-5'],
            'unknown role' => ['nobody@example.com:Nobody:5:owner'],
            'extra field' => ['nobody@example.com:Nobody:5:admin:extra'],
        ];
    }
}
