<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim((string) $credentials['login']);
        $userQuery = User::query()->where('status', 'active');
        if (str_contains($identifier, '@')) {
            $userQuery->where('email', $identifier);
        } else {
            $userQuery->where('username', $identifier);
        }
        $user = $userQuery->first();

        if (!$user) {
            return back()
                ->withErrors(['login' => 'Invalid credentials or inactive account.'])
                ->onlyInput('login');
        }

        $password = $credentials['password'];
        $storedHash = (string) $user->password_hash;
        $isValid = false;

        // Preferred path: Laravel hasher.
        try {
            $isValid = Hash::check($password, $storedHash);
        } catch (\RuntimeException) {
            // Fallback for legacy bcrypt prefixes (e.g. $2a$) from older systems.
            $isValid = password_verify($password, $storedHash);
        }

        if (!$isValid) {
            return back()
                ->withErrors(['login' => 'Invalid credentials or inactive account.'])
                ->onlyInput('login');
        }

        // Upgrade legacy hashes on successful login.
        $rehashNeeded = true;
        try {
            $rehashNeeded = Hash::needsRehash($storedHash);
        } catch (\RuntimeException) {
            $rehashNeeded = true;
        }

        if ($rehashNeeded) {
            $user->password_hash = Hash::make($password);
            $user->save();
        }

        Auth::login($user, (bool)$request->boolean('remember'));
        $request->session()->regenerate();
        $request->user()?->update(['last_login_at' => now()]);

        $isAdminPanelUser = (bool)$request->user()?->hasRole('Administrator', 'Treasurer', 'Secretary', 'Auditor');
        $isMemberOnlyUser = !empty($request->user()?->member_id) && !$isAdminPanelUser;

        if ($isMemberOnlyUser) {
            // Avoid redirecting member users to stale intended admin URLs.
            $request->session()->forget('url.intended');
            return redirect()->route('member-portal.index');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
