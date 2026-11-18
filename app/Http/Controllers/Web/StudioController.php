<?php

namespace App\Http\Controllers\Web;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreGenerationRequest;
use App\Http\Resources\Web\GenerationResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends Controller
{
    /**
     * The generate form and the user's generation history.
     */
    public function show(Request $request): Response
    {
        $generations = $request->user()->generations();

        return Inertia::render('web/Studio', [
            'generations' => GenerationResource::collection((clone $generations)->latest('id')->paginate(12)),
            'hasGenerationsInFlight' => (clone $generations)->inFlight()->exists(),
            'options' => [
                'sizes' => array_map(fn (ImageSize $size) => ['value' => $size->value, 'label' => $size->label()], ImageSize::cases()),
                'qualities' => array_column(ImageQuality::cases(), 'value'),
                'maxPromptLength' => StoreGenerationRequest::MAX_PROMPT_LENGTH,
                'maxReferenceImages' => StoreGenerationRequest::MAX_REFERENCE_IMAGES,
            ],
        ]);
    }
}
