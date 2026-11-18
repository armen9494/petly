<?php

namespace App\Support;

/**
 * A rough reading of a User-Agent header, enough to label a signed-in device
 * ("Chrome on macOS"). It recognizes common browsers and platforms only.
 */
final readonly class UserAgent
{
    /**
     * Checked in order: iOS and Android user agents also mention "Mac OS X" and "Linux".
     *
     * @var array<string, string>
     */
    private const array PLATFORMS = [
        '/iPhone|iPod/' => 'iOS',
        '/iPad/' => 'iPadOS',
        '/Android/' => 'Android',
        '/CrOS/' => 'ChromeOS',
        '/Windows/' => 'Windows',
        '/Macintosh|Mac OS X/' => 'macOS',
        '/Linux/' => 'Linux',
    ];

    /**
     * Checked in order: most browsers also mention "Chrome" and "Safari".
     *
     * @var array<string, string>
     */
    private const array BROWSERS = [
        '/Edg(e|A|iOS)?\//' => 'Edge',
        '/OPR\/|Opera/' => 'Opera',
        '/SamsungBrowser\//' => 'Samsung Internet',
        '/Firefox\/|FxiOS\//' => 'Firefox',
        '/Chrome\/|CriOS\//' => 'Chrome',
        '/Safari\//' => 'Safari',
    ];

    private const array MOBILE_PLATFORMS = ['iOS', 'iPadOS', 'Android'];

    private function __construct(
        public ?string $browser,
        public ?string $platform,
    ) {}

    public static function parse(?string $userAgent): self
    {
        return new self(
            browser: self::firstMatch(self::BROWSERS, (string) $userAgent),
            platform: self::firstMatch(self::PLATFORMS, (string) $userAgent),
        );
    }

    public function isMobile(): bool
    {
        return in_array($this->platform, self::MOBILE_PLATFORMS, true);
    }

    /**
     * "Chrome on macOS", or as much of that as is known.
     */
    public function label(): string
    {
        return match (true) {
            $this->browser !== null && $this->platform !== null => "{$this->browser} on {$this->platform}",
            default => $this->browser ?? $this->platform ?? 'Unknown device',
        };
    }

    /**
     * @param  array<string, string>  $patterns
     */
    private static function firstMatch(array $patterns, string $userAgent): ?string
    {
        foreach ($patterns as $pattern => $name) {
            if (preg_match($pattern, $userAgent) === 1) {
                return $name;
            }
        }

        return null;
    }
}
