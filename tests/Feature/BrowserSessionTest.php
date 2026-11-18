<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BrowserSessionTest extends TestCase
{
    use RefreshDatabase;

    private const string CHROME_ON_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    private const string SAFARI_ON_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';

    private const string FIREFOX_ON_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0';

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
    }

    #[TestWith(['/profile/sessions'])]
    #[TestWith(['/profile/sessions/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'])]
    public function test_guests_are_redirected_to_login(string $uri): void
    {
        $this->delete($uri)->assertRedirect('/login');
    }

    public function test_the_profile_page_lists_the_users_devices_with_this_device_first(): void
    {
        $this->travelTo(Carbon::parse('2026-11-17 12:00:00'));
        $user = User::factory()->create();
        $thisDevice = $this->createSession($user, self::CHROME_ON_MAC, now()->subMinutes(90), '203.0.113.10');
        $this->createSession($user, self::SAFARI_ON_IPHONE, now()->subMinutes(30), '198.51.100.7');
        $this->createSession(User::factory()->create(), self::FIREFOX_ON_WINDOWS);

        $this->actingAs($user)->fromDevice($thisDevice)->get('/profile')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('web/Profile')
            ->has('sessions', 2)
            ->has('sessions.0', fn (AssertableJson $session) => $session
                ->where('key', fn (string $key) => $key !== $thisDevice)
                ->where('device', 'Chrome on macOS')
                ->where('is_mobile', false)
                ->where('ip_address', '203.0.113.10')
                ->where('last_active_at', '2026-11-17T10:30:00+00:00')
                ->where('is_current_device', true))
            ->has('sessions.1', fn (AssertableJson $session) => $session
                ->whereType('key', 'string')
                ->where('device', 'Safari on iOS')
                ->where('is_mobile', true)
                ->where('ip_address', '198.51.100.7')
                ->where('last_active_at', '2026-11-17T11:30:00+00:00')
                ->where('is_current_device', false)));
    }

    public function test_signing_out_a_device_ends_only_that_devices_session(): void
    {
        $user = User::factory()->create();
        $thisDevice = $this->createSession($user, self::CHROME_ON_MAC);
        $phone = $this->createSession($user, self::SAFARI_ON_IPHONE);
        $laptop = $this->createSession($user, self::FIREFOX_ON_WINDOWS);
        $phoneKey = $this->listedSessions($user, $thisDevice)->firstWhere('device', 'Safari on iOS')['key'];

        $this->actingAs($user)->fromDevice($thisDevice)->from('/profile')->delete("/profile/sessions/{$phoneKey}")
            ->assertRedirect('/profile')
            ->assertInertiaFlash('success', 'Safari on iOS was signed out.');

        $this->assertDatabaseMissing('sessions', ['id' => $phone]);
        $this->assertDatabaseHas('sessions', ['id' => $laptop]);
        $this->assertDatabaseHas('sessions', ['id' => $thisDevice]);
    }

    public function test_another_users_device_cannot_be_signed_out(): void
    {
        $user = User::factory()->create();
        $thisDevice = $this->createSession($user, self::CHROME_ON_MAC);
        $stranger = User::factory()->create();
        $strangersDevice = $this->createSession($stranger, self::FIREFOX_ON_WINDOWS);
        $strangersKey = $this->listedSessions($stranger, $strangersDevice)->firstWhere('is_current_device', true)['key'];

        $this->actingAs($user)->fromDevice($thisDevice)->delete("/profile/sessions/{$strangersKey}")->assertNotFound();

        $this->assertDatabaseHas('sessions', ['id' => $strangersDevice, 'user_id' => $stranger->id]);
    }

    public function test_this_device_cannot_be_signed_out_from_the_list(): void
    {
        $user = User::factory()->create();
        $thisDevice = $this->createSession($user, self::CHROME_ON_MAC);
        $thisDeviceKey = $this->listedSessions($user, $thisDevice)->firstWhere('is_current_device', true)['key'];

        $this->actingAs($user)->fromDevice($thisDevice)->delete("/profile/sessions/{$thisDeviceKey}")->assertNotFound();

        $this->assertDatabaseHas('sessions', ['id' => $thisDevice]);
    }

    public function test_signing_out_all_other_devices_keeps_this_device_and_other_users_devices(): void
    {
        $user = User::factory()->create();
        $thisDevice = $this->createSession($user, self::CHROME_ON_MAC);
        $phone = $this->createSession($user, self::SAFARI_ON_IPHONE);
        $laptop = $this->createSession($user, self::FIREFOX_ON_WINDOWS);
        $strangersDevice = $this->createSession(User::factory()->create(), self::FIREFOX_ON_WINDOWS);

        $this->actingAs($user)->fromDevice($thisDevice)->from('/profile')->delete('/profile/sessions')
            ->assertRedirect('/profile')
            ->assertInertiaFlash('success', 'Signed out 2 other devices.');

        $this->assertDatabaseMissing('sessions', ['id' => $phone]);
        $this->assertDatabaseMissing('sessions', ['id' => $laptop]);
        $this->assertDatabaseHas('sessions', ['id' => $thisDevice]);
        $this->assertDatabaseHas('sessions', ['id' => $strangersDevice]);
    }

    public function test_signing_out_a_device_ends_remember_me_logins_on_other_devices(): void
    {
        $user = User::factory()->create();
        $thisDevice = $this->createSession($user, self::CHROME_ON_MAC);
        $this->createSession($user, self::SAFARI_ON_IPHONE);
        $phoneKey = $this->listedSessions($user, $thisDevice)->firstWhere('device', 'Safari on iOS')['key'];
        $rememberedLogin = $this->rememberCookie($user);
        $this->assertRememberCookieLogsIn($rememberedLogin);

        $this->startFreshBrowser();
        $this->actingAs($user)->fromDevice($thisDevice)->delete("/profile/sessions/{$phoneKey}")->assertRedirect();

        $this->startFreshBrowser();
        $this->withCookie(...$rememberedLogin)->get('/profile')->assertRedirect('/login');
    }

    public function test_signing_out_all_other_devices_ends_their_remember_me_logins(): void
    {
        $user = User::factory()->create();
        $thisDevice = $this->createSession($user, self::CHROME_ON_MAC);
        $rememberedLogin = $this->rememberCookie($user);
        $this->assertRememberCookieLogsIn($rememberedLogin);

        $this->startFreshBrowser();
        $this->actingAs($user)->fromDevice($thisDevice)->delete('/profile/sessions')->assertRedirect();

        $this->startFreshBrowser();
        $this->withCookie(...$rememberedLogin)->get('/profile')->assertRedirect('/login');
    }

    public function test_this_device_stays_remembered_after_signing_out_other_devices(): void
    {
        $user = User::factory()->create();
        $thisDevice = $this->createSession($user, self::CHROME_ON_MAC);
        [$name, $value] = $this->rememberCookie($user);

        $response = $this->actingAs($user)->fromDevice($thisDevice)->withCookie($name, $value)->delete('/profile/sessions')
            ->assertCookie($name);

        $this->assertRememberCookieLogsIn([$name, $response->getCookie($name)->getValue()]);
    }

    /**
     * Insert a session row the way the database session handler writes one.
     */
    private function createSession(User $user, string $userAgent, ?Carbon $lastActivity = null, string $ipAddress = '203.0.113.10'): string
    {
        $id = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'payload' => '',
            'last_activity' => ($lastActivity ?? now())->getTimestamp(),
        ]);

        return $id;
    }

    /**
     * Send the next request with this session's cookie.
     */
    private function fromDevice(string $sessionId): static
    {
        return $this->withCookie(config('session.cookie'), $sessionId);
    }

    /**
     * The devices the profile page lists for the user, viewed from one of their sessions.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function listedSessions(User $user, string $sessionId): Collection
    {
        return collect($this->actingAs($user)->fromDevice($sessionId)->get('/profile')->inertiaProps('sessions'));
    }

    /**
     * A "Remember me" cookie for the user's current remember token.
     *
     * @return array{string, string}
     */
    private function rememberCookie(User $user): array
    {
        $guard = Auth::guard();

        return [
            $guard->getRecallerName(),
            $user->id.'|'.$user->getRememberToken().'|'.$guard->hashPasswordForCookie($user->getAuthPassword()),
        ];
    }

    /**
     * @param  array{string, string}  $rememberCookie
     */
    private function assertRememberCookieLogsIn(array $rememberCookie): void
    {
        $this->startFreshBrowser();

        $this->withCookie(...$rememberCookie)->get('/profile')->assertOk();
    }

    /**
     * Make the next request come from a browser with no cookies and nobody logged in.
     */
    private function startFreshBrowser(): void
    {
        $this->defaultCookies = [];
        $this->app['auth']->forgetGuards();
        $this->app['session.store']->flush();
    }
}
