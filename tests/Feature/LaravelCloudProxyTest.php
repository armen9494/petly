<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * On Laravel Cloud the app sits behind Cloud's load balancer, which connects
 * from a private address over plain HTTP and describes the real visitor in
 * X-Forwarded-* headers. The app boots in Cloud mode for every test here.
 */
class LaravelCloudProxyTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['LARAVEL_CLOUD'] = '1';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($_SERVER['LARAVEL_CLOUD']);
    }

    public function test_a_request_through_the_load_balancer_gets_https_links_on_the_public_host(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.4.12'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'petly.laravel.cloud',
                'X-Forwarded-For' => '198.51.100.7',
            ])
            ->get('http://petly.test/')
            ->assertRedirect('https://petly.laravel.cloud/login');
    }
}
