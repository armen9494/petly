<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateProfileRequest;
use App\Services\BrowserSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * The profile form and the devices the user is signed in on. The user's name
     * and email come from the shared auth props.
     */
    public function edit(Request $request, BrowserSessions $sessions): Response
    {
        return Inertia::render('web/Profile', [
            'sessions' => $sessions->forUser($request->user(), $request->session()->getId()),
        ]);
    }

    /**
     * Changing the email clears its verification, since the new address hasn't been verified.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back();
    }
}
