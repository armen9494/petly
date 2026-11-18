<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GenerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\Admin\GenerationResource;
use App\Http\Resources\Admin\UserResource;
use App\Http\Resources\Shared\CreditTransactionResource;
use App\Models\User;
use App\Services\CreditLedger;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const array ROLES = ['admin', 'user'];

    private const array STATUSES = ['active', 'inactive'];

    private const int RECENT_GENERATIONS = 10;

    /**
     * Every user, newest first, with their generation count and balance,
     * narrowed by any mix of search, role and status.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(self::ROLES)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);

        $search = $validated['search'] ?? null;
        $role = $validated['role'] ?? null;
        $status = $validated['status'] ?? null;

        $users = User::query()
            ->withCount('generations')
            ->withSum('creditTransactions', 'amount')
            ->when($search, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->when($role, fn (Builder $query, string $role) => $query->where('is_admin', $role === 'admin'))
            ->when($status, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/users/Index', [
            'users' => UserResource::collection($users),
            'filters' => ['search' => $search, 'role' => $role, 'status' => $status],
        ]);
    }

    /**
     * One user's account, credits and recent activity. Credits are read-only here.
     */
    public function show(Request $request, User $user, CreditLedger $ledger): Response
    {
        return Inertia::render('admin/users/Show', [
            'user' => UserResource::make($user)->resolve($request),
            'isCurrentUser' => $user->is($request->user()),
            // Not "credits": that shared prop is the signed-in admin's own balance in the header.
            'userCredits' => $ledger->summary($user),
            'stats' => $this->stats($user),
            'generations' => GenerationResource::collection(
                $user->generations()->latest('id')->limit(self::RECENT_GENERATIONS)->get(),
            )->resolve($request),
            'transactions' => CreditTransactionResource::collection(
                $user->creditTransactions()->latest('id')->paginate(15),
            ),
        ]);
    }

    /**
     * Change a user's name, email and whether their account is active.
     *
     * A new email hasn't been verified, so changing it clears the verification,
     * as it does when users change it themselves.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->only(['name', 'email']));
        $user->is_active = $request->boolean('is_active');

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back();
    }

    /**
     * The user's request counts and spend. "in_flight" counts requests that
     * haven't finished yet: pending, queued or processing.
     *
     * @return array{generations: int, succeeded: int, failed: int, in_flight: int, spent: string}
     */
    private function stats(User $user): array
    {
        $succeeded = fn (Builder $query) => $query->where('status', GenerationStatus::Succeeded);

        $user->loadCount([
            'generations',
            'generations as succeeded_generations_count' => $succeeded,
            'generations as failed_generations_count' => fn (Builder $query) => $query->where('status', GenerationStatus::Failed),
            'generations as in_flight_generations_count' => fn (Builder $query) => $query->inFlight(),
        ])->loadSum(['generations as spent' => $succeeded], 'price');

        return [
            'generations' => $user->generations_count,
            'succeeded' => $user->succeeded_generations_count,
            'failed' => $user->failed_generations_count,
            'in_flight' => $user->in_flight_generations_count,
            'spent' => Money::toDecimal((int) $user->spent),
        ];
    }
}
