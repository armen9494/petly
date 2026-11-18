<?php

namespace App\Services;

use App\Models\User;
use App\Support\UserAgent;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The devices a user is signed in on, read from the database session store.
 *
 * Session IDs are bearer secrets, so they never leave the server: each session
 * is identified to the browser by a hash of its ID instead.
 */
final class BrowserSessions
{
    /**
     * The user's sessions, this device first, then the most recently active.
     *
     * @return list<array{key: string, device: string, is_mobile: bool, ip_address: ?string, last_active_at: string, is_current_device: bool}>
     */
    public function forUser(User $user, string $currentSessionId): array
    {
        return $this->sessionsOf($user)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(function (object $session) use ($currentSessionId): array {
                $userAgent = UserAgent::parse($session->user_agent);

                return [
                    'key' => $this->keyFor($session->id),
                    'device' => $userAgent->label(),
                    'is_mobile' => $userAgent->isMobile(),
                    'ip_address' => $session->ip_address,
                    'last_active_at' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                    'is_current_device' => $session->id === $currentSessionId,
                ];
            })
            ->sortByDesc('is_current_device')
            ->values()
            ->all();
    }

    /**
     * Delete one of the user's other sessions.
     *
     * @return string|null The signed-out device's label, or null when the user has no other session with that key.
     */
    public function delete(User $user, string $key, string $currentSessionId): ?string
    {
        $session = $this->sessionsOf($user)
            ->where('id', '!=', $currentSessionId)
            ->get(['id', 'user_agent'])
            ->first(fn (object $session): bool => hash_equals($this->keyFor($session->id), $key));

        if ($session === null) {
            return null;
        }

        $this->table()->where('id', $session->id)->delete();

        return UserAgent::parse($session->user_agent)->label();
    }

    /**
     * Delete every session of the user's except the current one.
     *
     * @return int How many sessions were deleted.
     */
    public function deleteOthers(User $user, string $currentSessionId): int
    {
        return $this->sessionsOf($user)->where('id', '!=', $currentSessionId)->delete();
    }

    private function keyFor(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    private function sessionsOf(User $user): Builder
    {
        return $this->table()->where('user_id', $user->getKey());
    }

    private function table(): Builder
    {
        return DB::connection(config('session.connection'))->table(config('session.table'));
    }
}
