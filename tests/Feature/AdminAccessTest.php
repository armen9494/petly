<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every admin page and action is closed to guests and to users who aren't
 * admins. The spend report's own test covers /admin/report.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function adminRoutes(): array
    {
        return [
            'overview' => ['GET', '/admin'],
            'user list' => ['GET', '/admin/users'],
            'user page' => ['GET', '/admin/users/{user}'],
            'user update' => ['PATCH', '/admin/users/{user}'],
            'generations' => ['GET', '/admin/generations'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_users_who_are_not_admins_are_forbidden(string $method, string $uri): void
    {
        $target = User::factory()->create(['name' => 'Ada']);

        $this->actingAs(User::factory()->create())
            ->call($method, $this->uriFor($uri, $target), ['name' => 'Changed', 'email' => 'changed@example.com', 'is_active' => false])
            ->assertForbidden();

        $this->assertSame('Ada', $target->refresh()->name);
        $this->assertTrue($target->is_active);
    }

    #[DataProvider('adminRoutes')]
    public function test_guests_are_redirected_to_login(string $method, string $uri): void
    {
        $target = User::factory()->create();

        $this->call($method, $this->uriFor($uri, $target))->assertRedirect('/login');
    }

    private function uriFor(string $uri, User $user): string
    {
        return str_replace('{user}', (string) $user->id, $uri);
    }
}
