<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The ngrok tunnel (`valet share`) connects from this machine over plain HTTP
 * and describes the real visitor in X-Forwarded-* headers.
 */
class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_request_through_a_local_tunnel_gets_https_links_on_the_public_host(): void
    {
        $this->throughTunnel(from: '127.0.0.1', visitorIp: '198.51.100.7')
            ->get('http://petly.test/')
            ->assertRedirect('https://petly.ngrok.app/login');
    }

    public function test_forwarded_headers_from_any_other_address_are_ignored(): void
    {
        $this->throughTunnel(from: '203.0.113.9', visitorIp: '198.51.100.7')
            ->get('http://petly.test/')
            ->assertRedirect('http://petly.test/login');
    }

    public function test_each_visitor_through_the_tunnel_has_their_own_login_rate_limit(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->throughTunnel(from: '127.0.0.1', visitorIp: '198.51.100.7')
                ->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->throughTunnel(from: '127.0.0.1', visitorIp: '198.51.100.8')
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    private function throughTunnel(string $from, string $visitorIp): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $from])->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'petly.ngrok.app',
            'X-Forwarded-For' => $visitorIp,
        ]);
    }
}
