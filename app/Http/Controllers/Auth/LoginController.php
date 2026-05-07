<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = \App\Models\User::where('email', $credentials['email'])->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => 'No account with this email.',
            ]);
        }

        if ($user->isLocked()) {
            throw ValidationException::withMessages([
                'email' => "Account locked until {$user->locked_until->format('h:i A')}. Try again later.",
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Account is inactive. Contact your administrator.',
            ]);
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            $user->recordFailedLogin();
            throw ValidationException::withMessages([
                'password' => 'Invalid credentials.',
            ]);
        }

        $request->session()->regenerate();
        $user->recordLogin($request->ip(), $request->userAgent());

        if (!$user->is_super_admin && !$user->default_property_id && $user->properties()->count() === 0) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'No property assigned to your account. Contact your administrator.',
            ]);
        }

        return redirect()->intended($user->is_super_admin ? route('super.dashboard') : route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
