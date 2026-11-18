<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GenerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\GenerationResource;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    /**
     * Every user's generation requests, newest first, with what each was
     * expected to cost and what it actually cost.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(GenerationStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'user' => ['nullable', 'integer'],
        ]);

        $status = $validated['status'] ?? null;
        $search = $validated['search'] ?? null;

        // A link to a user who doesn't exist is a 404, rather than a silent
        // redirect back to whatever list was open before.
        $user = isset($validated['user']) ? User::query()->findOrFail($validated['user'], ['id', 'name', 'email']) : null;

        $generations = Generation::query()
            ->with('user:id,name,email')
            ->when($status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($search, fn (Builder $query, string $search) => $query->whereLike('prompt', "%{$search}%"))
            ->when($user, fn (Builder $query, User $user) => $query->whereBelongsTo($user))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/Generations', [
            'generations' => GenerationResource::collection($generations),
            'statuses' => array_column(GenerationStatus::cases(), 'value'),
            'filters' => [
                'status' => $status,
                'search' => $search,
                'user' => $user?->only('id', 'name', 'email'),
            ],
        ]);
    }
}
