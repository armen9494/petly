<?php

namespace App\Policies;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GenerationPolicy
{
    /**
     * Only a generation's owner can see it. Anyone else gets a 404, so ids
     * don't reveal which generations exist.
     */
    public function view(User $user, Generation $generation): Response
    {
        return $generation->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
