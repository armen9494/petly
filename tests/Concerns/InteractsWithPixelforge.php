<?php

namespace Tests\Concerns;

use App\Models\Generation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait InteractsWithPixelforge
{
    protected string $webhookSecret = 'whsec_test_secret';

    protected function configurePixelforge(): void
    {
        config([
            'services.pixelforge.base_url' => 'https://api.pixelforge.example/v1',
            'services.pixelforge.api_key' => 'pf_test_key',
            'services.pixelforge.webhook_secret' => $this->webhookSecret,
        ]);

        Http::preventStrayRequests();
    }

    /**
     * The GET /v1/pricing response from the docs.
     *
     * @return array<string, mixed>
     */
    protected function pricingPayload(): array
    {
        return [
            'object' => 'pricing',
            'currency' => 'usd',
            'unit' => 'per_1m_tokens',
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'tokens' => [
                'characters_per_text_token' => 4,
                'text_token_variance' => 0.15,
                'per_reference_image' => 765,
                'output' => [
                    'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                    'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                    'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
                ],
                'reasoning' => [
                    'low' => ['min' => 0, 'max' => 500],
                    'medium' => ['min' => 200, 'max' => 2000],
                    'high' => ['min' => 1000, 'max' => 8000],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $responses  Extra URL patterns to fake.
     */
    protected function fakePixelforge(array $responses = []): void
    {
        Http::fake([
            ...$responses,
            '*/v1/pricing' => Http::response($this->pricingPayload()),
        ]);
    }

    /**
     * A Pixelforge generation object for one of our generations.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function remoteGeneration(Generation $generation, string $status, array $overrides = []): array
    {
        $succeeded = $status === 'succeeded';
        $failed = $status === 'failed';
        $id = $generation->pixelforge_id ?? 'gen_'.Str::upper(Str::random(26));

        return array_replace([
            'id' => $id,
            'object' => 'generation',
            'status' => $status,
            'prompt' => $generation->prompt,
            'size' => $generation->size->value,
            'quality' => $generation->quality->value,
            'reference_images' => $generation->reference_images,
            'metadata' => [Generation::METADATA_KEY => $generation->uuid, 'user_id' => (string) $generation->user_id],
            'image_url' => $succeeded ? "https://pixelforge.promptedit.dev/images/{$id}.png?signature=abc" : null,
            'image_url_expires_at' => $succeeded ? now()->addDay()->toIso8601ZuluString() : null,
            'usage' => $succeeded ? [
                'input_text_tokens' => 8,
                'input_image_tokens' => 0,
                'output_tokens' => 1100,
                'reasoning_tokens' => 1000,
                'total_tokens' => 2108,
            ] : null,
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'error' => $failed ? ['code' => 'generation_failed', 'message' => "The model couldn't produce an image for this prompt."] : null,
            'created_at' => now()->subSeconds(20)->toIso8601ZuluString(),
            'started_at' => in_array($status, ['processing', 'succeeded', 'failed'], true) ? now()->subSeconds(15)->toIso8601ZuluString() : null,
            'completed_at' => $succeeded || $failed ? now()->toIso8601ZuluString() : null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    protected function webhookEvent(string $type, array $object, ?string $id = null): array
    {
        return [
            'id' => $id ?? 'evt_'.Str::upper(Str::random(26)),
            'object' => 'event',
            'type' => $type,
            'created_at' => now()->toIso8601ZuluString(),
            'data' => ['object' => $object],
        ];
    }

    /**
     * POST a webhook signed the way Pixelforge signs them.
     *
     * @param  array<string, mixed>  $event
     * @param  int  $jsonFlags  json_encode() flags, to vary the raw body's bytes.
     */
    protected function postWebhook(array $event, ?int $timestamp = null, ?string $secret = null, int $jsonFlags = 0): TestResponse
    {
        $body = json_encode($event, $jsonFlags);
        $timestamp ??= time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret ?? $this->webhookSecret);

        return $this->call('POST', '/webhooks/pixelforge', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_EVENT_ID' => $event['id'],
            'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], content: $body);
    }
}
