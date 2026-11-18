<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BrowserSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BrowserSessionController extends Controller
{
    /**
     * Sign out one of the user's other devices.
     */
    public function destroy(Request $request, BrowserSessions $sessions, string $session): RedirectResponse
    {
        $device = $sessions->delete($request->user(), $session, $request->session()->getId());

        abort_if($device === null, 404);

        $this->endRememberedLogins($request);

        Inertia::flash('success', "{$device} was signed out.");

        return back();
    }

    /**
     * Sign out every device except this one.
     */
    public function destroyOthers(Request $request, BrowserSessions $sessions): RedirectResponse
    {
        $count = $sessions->deleteOthers($request->user(), $request->session()->getId());

        $this->endRememberedLogins($request);

        Inertia::flash('success', "Signed out {$count} other ".Str::plural('device', $count).'.');

        return back();
    }

    /**
     * Deleting a session isn't enough for a device that ticked "Remember me": its
     * cookie would log it straight back in. Remember tokens are per user, not per
     * device, so replace the token, which ends every device's remembered login, as
     * logging out does, then remember this device again if it was remembered.
     */
    private function endRememberedLogins(Request $request): void
    {
        $user = $request->user();
        $guard = Auth::guard();

        if (blank($user->getRememberToken())) {
            return;
        }

        $guard->getProvider()->updateRememberToken($user, Str::random(60));

        if ($request->hasCookie($guard->getRecallerName())) {
            $guard->login($user, remember: true);
        }
    }
}
