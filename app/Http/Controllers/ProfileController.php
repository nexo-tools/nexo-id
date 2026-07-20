<?php

namespace App\Http\Controllers;

use App\Support\SessionSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        // Persist the current session so it appears in its own listing and is
        // flagged as the current device (AC-SESS-1).
        $request->session()->save();

        return view('profile.show', [
            'user' => $request->user(),
            'sessions' => $this->sessions($request),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:100'],
        ]);

        $request->user()->update($validated);

        return back()->with('status', __('Your profile was updated.'));
    }

    /**
     * The user's active sessions from the database session store, newest first,
     * with the current one flagged (AC-SESS-1).
     *
     * @return Collection<int, SessionSummary>
     */
    private function sessions(Request $request): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        $currentId = $request->session()->getId();

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session): SessionSummary => new SessionSummary(
                id: $session->id,
                ipAddress: $session->ip_address,
                userAgent: $session->user_agent,
                lastActive: Carbon::createFromTimestamp($session->last_activity),
                isCurrent: $session->id === $currentId,
            ));
    }
}
