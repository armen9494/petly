<?php

namespace App\Services\Pixelforge;

use Closure;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A thin client for the Pixelforge API.
 *
 * Error responses throw {@see PixelforgeException}; network failures throw
 * {@see ConnectionException}. Retrying is left to the caller, which knows
 * whether it is safe.
 */
final readonly class PixelforgeClient
{
    public function __construct(
        private string $baseUrl,
        private ?string $apiKey,
        private int $timeoutSeconds = 15,
    ) {}

    /**
     * Create a generation. Retrying with the same idempotency key never creates
     * a second one, so this is safe to call again after a timeout.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed> The generation object.
     *
     * @throws PixelforgeException|ConnectionException
     */
    public function createGeneration(array $attributes, string $idempotencyKey): array
    {
        return $this->send(fn (PendingRequest $http) => $http
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->post('generations', $attributes));
    }

    /**
     * @return array<string, mixed> The generation object.
     *
     * @throws PixelforgeException|ConnectionException
     */
    public function generation(string $id): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get('generations/'.rawurlencode($id)));
    }

    /**
     * What Pixelforge billed this API key, per UTC day.
     *
     * @return array<string, mixed>
     *
     * @throws PixelforgeException|ConnectionException
     */
    public function usage(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get('usage', [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]));
    }

    /**
     * The current rates. Cached for five minutes, and served stale for up to
     * an hour while it refreshes, so estimates stay fast.
     *
     * @throws PixelforgeException|ConnectionException
     */
    public function pricing(): Pricing
    {
        $data = Cache::flexible('pixelforge.pricing', [300, 3600], fn () => $this->send(
            fn (PendingRequest $http) => $http->get('pricing'),
            authenticated: false,
        ));

        return Pricing::fromApi($data);
    }

    /**
     * @param  Closure(PendingRequest): Response  $request
     * @return array<string, mixed>
     */
    private function send(Closure $request, bool $authenticated = true): array
    {
        $http = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout($this->timeoutSeconds);

        if ($authenticated) {
            $http->withToken($this->apiKey ?: throw PixelforgeException::missingApiKey());
        }

        $response = $request($http);

        if ($response->failed()) {
            throw PixelforgeException::fromResponse($response);
        }

        return $response->json();
    }
}
