<?php

namespace Tests\Unit;

use App\Support\UserAgent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserAgentTest extends TestCase
{
    /**
     * @return array<string, array{?string, string, bool}>
     */
    public static function userAgents(): array
    {
        return [
            'Chrome on macOS' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'Chrome on macOS', false],
            'Safari on macOS' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15', 'Safari on macOS', false],
            'Edge on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0', 'Edge on Windows', false],
            'Firefox on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0', 'Firefox on Windows', false],
            'Opera on Linux' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 OPR/116.0.0.0', 'Opera on Linux', false],
            'Chrome on ChromeOS' => ['Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'Chrome on ChromeOS', false],
            'Safari on iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1', 'Safari on iOS', true],
            'Chrome on iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/131.0.6778.73 Mobile/15E148 Safari/604.1', 'Chrome on iOS', true],
            'Firefox on iPad' => ['Mozilla/5.0 (iPad; CPU OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/133.0 Mobile/15E148 Safari/605.1.15', 'Firefox on iPadOS', true],
            'Chrome on Android' => ['Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36', 'Chrome on Android', true],
            'Samsung Internet on Android' => ['Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0.0.0 Mobile Safari/537.36', 'Samsung Internet on Android', true],
            'browser only' => ['Mozilla/5.0 Firefox/133.0', 'Firefox', false],
            'platform only' => ['SomeApp/1.0 (Windows NT 10.0)', 'Windows', false],
            'unrecognized' => ['curl/8.7.1', 'Unknown device', false],
            'missing' => [null, 'Unknown device', false],
        ];
    }

    #[DataProvider('userAgents')]
    public function test_it_labels_the_browser_and_platform(?string $userAgent, string $label, bool $isMobile): void
    {
        $parsed = UserAgent::parse($userAgent);

        $this->assertSame($label, $parsed->label());
        $this->assertSame($isMobile, $parsed->isMobile());
    }
}
