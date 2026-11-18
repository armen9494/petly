<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\Pixelforge\GenerationSynchronizer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Receives Pixelforge's webhooks (signatures are checked by middleware).
 *
 * Each event is recorded and applied in one transaction: the unique event_id
 * turns a redelivery into a no-op, and if applying fails nothing is recorded,
 * so we answer with an error and Pixelforge retries.
 */
class PixelforgeWebhookController extends Controller
{
    public function store(Request $request, GenerationSynchronizer $synchronizer): Response
    {
        $event = $request->validate([
            'id' => ['required', 'string'],
            'type' => ['required', 'string'],
        ]);

        $remote = $this->generationFrom($request, $event['type']);

        DB::transaction(function () use ($event, $remote, $request, $synchronizer): void {
            $recorded = WebhookEvent::query()->insertOrIgnore([
                'event_id' => $event['id'],
                'type' => $event['type'],
                'pixelforge_generation_id' => $remote['id'] ?? null,
                'payload' => $request->getContent(),
                'received_at' => now(),
            ]);

            if ($recorded === 0) {
                Log::info('Ignored a duplicate Pixelforge webhook.', ['event' => $event['id']]);

                return;
            }

            if ($remote === null) {
                return;
            }

            $generation = Generation::findForPixelforge($remote);

            if ($generation === null) {
                Log::warning('Received a Pixelforge webhook for an unknown generation.', [
                    'event' => $event['id'],
                    'generation' => $remote['id'],
                ]);

                return;
            }

            $synchronizer->apply($generation, $remote);
        });

        return response()->noContent();
    }

    /**
     * The generation object carried by a generation.* event; null for other
     * events, such as the dashboard's "ping".
     *
     * @return array<string, mixed>|null
     */
    private function generationFrom(Request $request, string $type): ?array
    {
        if (! str_starts_with($type, 'generation.')) {
            return null;
        }

        $request->validate([
            'data.object.id' => ['required', 'string'],
            'data.object.status' => ['required', 'in:queued,processing,succeeded,failed'],
        ]);

        return $request->input('data.object');
    }
}
