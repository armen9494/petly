<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * An error response from the Pixelforge API.
 */
final class PixelforgeException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response): self
    {
        $error = $response->json('error');
        $retryAfter = $response->header('Retry-After');

        return new self(
            message: $error['message'] ?? "Pixelforge responded with HTTP {$response->status()}.",
            errorCode: $error['code'] ?? "http_{$response->status()}",
            status: $response->status(),
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }

    public static function missingApiKey(): self
    {
        return new self('No Pixelforge API key is configured.', 'invalid_api_key', 401);
    }

    /**
     * Whether the same request may succeed if we wait and try again.
     */
    public function isTransient(): bool
    {
        return match ($this->errorCode) {
            'rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded' => true,
            'daily_limit_exceeded' => false,
            default => $this->status >= 500,
        };
    }

    /**
     * What to tell the user while we wait to retry.
     */
    public function waitingMessage(): string
    {
        return match ($this->errorCode) {
            'concurrency_limit_exceeded' => 'Waiting for a free slot: Pixelforge runs 3 generations at a time.',
            'rate_limit_exceeded' => 'Pixelforge is rate limiting us. Retrying shortly.',
            default => 'Pixelforge is overloaded. Retrying shortly.',
        };
    }

    /**
     * Why the generation could not be started, phrased for the user.
     */
    public function userMessage(): string
    {
        return match ($this->errorCode) {
            'content_policy_violation' => 'Pixelforge rejected this prompt under its content policy. Try rewording it.',
            'daily_limit_exceeded' => "Today's generation quota is used up. It resets at midnight UTC.",
            'rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded' => 'Pixelforge stayed too busy to accept this generation. Please try again.',
            'validation_failed' => "Pixelforge rejected the request: {$this->getMessage()}",
            'invalid_api_key' => 'Image generation is not configured correctly. We have been notified.',
            default => 'Pixelforge could not accept this generation. Please try again.',
        };
    }
}
