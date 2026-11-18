<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GenerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\GenerationResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\Generation;
use App\Models\User;
use App\Services\AdminOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const int LIST_LENGTH = 5;

    /**
     * The admin overview: headline numbers, the newest users and the latest
     * failed requests.
     */
    public function show(Request $request, AdminOverview $overview): Response
    {
        $latestFailures = Generation::query()
            ->with('user:id,name,email')
            ->where('status', GenerationStatus::Failed)
            ->latest('id')
            ->limit(self::LIST_LENGTH)
            ->get();

        return Inertia::render('admin/Dashboard', [
            'stats' => $overview->stats(),
            'stuckAfterMinutes' => AdminOverview::STUCK_AFTER_MINUTES,
            'newestUsers' => UserResource::collection(User::query()->latest('id')->limit(self::LIST_LENGTH)->get())->resolve($request),
            'latestFailures' => GenerationResource::collection($latestFailures)->resolve($request),
        ]);
    }
}
