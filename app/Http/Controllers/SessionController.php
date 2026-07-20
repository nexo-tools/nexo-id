<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    /**
     * Revoke one specific other session of the current user (AC-SESS-2).
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('status', __('That session was signed out.'));
    }

    /**
     * Revoke every other session, keeping the current one (AC-SESS-3).
     */
    public function destroyOthers(Request $request): RedirectResponse
    {
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('status', __('All other sessions were signed out.'));
    }
}
