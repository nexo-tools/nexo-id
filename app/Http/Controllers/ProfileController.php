<?php

namespace App\Http\Controllers;

use App\Actions\DeleteAccount;
use App\Auth\ConnectedTools;
use App\Auth\IdpSessions;
use App\Models\User;
use App\Support\SessionSummary;
use App\Support\UserAgent;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private ConnectedTools $tools,
        private IdpSessions $idpSessions,
    ) {}

    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('profile.show', [
            'user' => $user,
            'sessions' => $this->sessions($request),
            'connectedTools' => $this->tools->forUser($user),
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
     * «Borrar mi cuenta» (H-nexoid-10), confirmed with the password. Every tool
     * is told first (DeleteAccount), then the account goes; the person lands
     * on the landing with a notice, signed out.
     */
    public function destroy(Request $request, DeleteAccount $deleteAccount): RedirectResponse
    {
        $request->validateWithBag('deleteAccount', [
            'delete_password' => ['required', 'string', 'current_password:web'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $deleteAccount($user);

        // logoutCurrentDevice, not logout(): logout() writes a new remember
        // token on the user, and saving a deleted model would INSERT it again.
        $guard = Auth::guard('web');
        if ($guard instanceof SessionGuard) {
            $guard->logoutCurrentDevice();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('Your Nexo ID account was deleted.'));
    }

    /**
     * The user's active sessions from the database session store, current
     * device first, then by last activity (AC-SESS-1, H-nexoid-06): browser
     * and system out of the user agent, and the tools each one signed in to.
     *
     * No session()->save() here any more: saving mid-request ages the flash
     * data, so the «guardado» notices of this very page were wiped before the
     * view could read them (H-nexoid-07). The current device is listed from
     * the request when its row is not written yet (it is, at the end of the
     * request).
     *
     * @return Collection<int, SessionSummary>
     */
    private function sessions(Request $request): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        /** @var User $user */
        $user = $request->user();
        $currentId = $request->session()->getId();

        $rows = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get();

        $sids = [];
        foreach ($rows as $row) {
            $sids[$row->id] = $row->id === $currentId
                ? $this->idpSessions->current($request->session())
                : SessionController::sidFromPayload((string) $row->payload);
        }

        if (! $rows->contains('id', $currentId)) {
            $rows->prepend((object) [
                'id' => $currentId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'last_activity' => now()->getTimestamp(),
            ]);
            $sids[$currentId] = $this->idpSessions->current($request->session());
        }

        $toolsBySid = $this->tools->namesBySid(array_values(array_filter($sids)));

        return $rows
            ->map(function (object $session) use ($currentId, $sids, $toolsBySid): SessionSummary {
                $agent = UserAgent::parse($session->user_agent);
                $sid = $sids[$session->id] ?? null;

                return new SessionSummary(
                    id: $session->id,
                    ipAddress: $session->ip_address,
                    userAgent: $session->user_agent,
                    lastActive: Carbon::createFromTimestamp($session->last_activity),
                    isCurrent: $session->id === $currentId,
                    browser: $agent['browser'],
                    system: $agent['system'],
                    tools: $sid !== null ? ($toolsBySid[$sid] ?? []) : [],
                );
            })
            ->sortByDesc(fn (SessionSummary $s): int => $s->isCurrent ? PHP_INT_MAX : $s->lastActive->getTimestamp())
            ->values();
    }
}
