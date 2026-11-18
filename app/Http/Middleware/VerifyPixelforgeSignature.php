<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject webhook requests that weren't signed by Pixelforge.
 *
 * The Pixelforge-Signature header is "t=<unix time>,v1=<hex HMAC-SHA256 of
 * "<t>.<raw body>">", keyed with our API key's signing secret. Old timestamps
 * are rejected so a captured request can't be replayed later.
 */
class VerifyPixelforgeSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.pixelforge.webhook_secret');

        if (blank($secret)) {
            Log::critical('Received a Pixelforge webhook but PIXELFORGE_WEBHOOK_SECRET is not set.');

            return response()->json(['error' => 'Webhooks are not configured.'], 500);
        }

        if (! $this->hasValidSignature($request, $secret)) {
            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        return $next($request);
    }

    private function hasValidSignature(Request $request, string $secret): bool
    {
        $parts = [];

        foreach (explode(',', (string) $request->header('Pixelforge-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        if (! ctype_digit($timestamp) || $signature === '') {
            return false;
        }

        if (abs(time() - (int) $timestamp) > config('services.pixelforge.webhook_tolerance')) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", $secret);

        return hash_equals($expected, $signature);
    }
}
