<?php

namespace App\Http\Controllers\Auth;

use App\Auth\SignUpDestination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * The sign-up screen. When the person came from a tool (its «Crear cuenta»
     * with prompt=create, or the login of its authorize request), it says which
     * one: verifying the email sends them back there (url.intended).
     */
    public function create(Request $request, SignUpDestination $destination): View
    {
        return view('auth.register', [
            'tool' => $destination->toolName($request->session()->get('url.intended')),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create($request->only('display_name', 'email', 'password'));

        // Fires the listener that emails the signed verification link (AC-REG-1).
        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
