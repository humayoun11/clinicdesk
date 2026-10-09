<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordRequest;
use App\Http\Requests\ProfileRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function form(string $form = 'login')
    {
        abort_unless(in_array($form, ['login', 'register', 'forgot-password']), 404);

        return view('auth.form', compact('form'));
    }

    public function register(RegisterRequest $r)
    {
        $u = User::create($r->safe()->only(['name', 'email', 'password']));
        event(new Registered($u));
        if ($r->is('api/*')) {
            return (new UserResource($u))->response()->setStatusCode(201);
        }
        Auth::login($u);
        $r->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function login(LoginRequest $r)
    {
        $isApi = $r->is('api/*');
        $authenticated = $isApi ? Auth::validate($r->validated()) : Auth::attempt($r->validated());
        if (! $authenticated) {
            throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
        }
        if ($isApi) {
            $u = User::where('email', $r->email)->firstOrFail();
            $expires = now()->addMinutes(config('clinic.token_minutes'));
            $token = $u->createToken('clinicdesk', ['*'], $expires);

            return response()->json(['data' => ['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => $expires->toIso8601String(), 'user' => new UserResource($u)]]);
        }
        $r->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $r)
    {
        if ($r->is('api/*')) {
            $r->user()->currentAccessToken()->delete();

            return response()->noContent();
        }
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/')->with('success', 'You have signed out.');
    }

    public function profile()
    {
        return view('auth.profile');
    }

    public function updateProfile(ProfileRequest $r)
    {
        $r->user()->update($r->validated());

        return back()->with('success', 'Profile updated.');
    }

    public function changePassword(PasswordRequest $r)
    {
        $r->user()->update(['password' => $r->password, 'remember_token' => Str::random(60)]);
        $r->user()->tokens()->delete();
        $r->session()->regenerate();

        return back()->with('success', 'Password updated and API tokens revoked.');
    }

    public function forgot(Request $r)
    {
        $r->validate(['email' => 'required|email|max:254']);
        Password::sendResetLink($r->only('email'));

        return back()->with('success', 'If that email is registered, a reset link has been sent. For local demo delivery, check the mail log.');
    }

    public function resetForm(Request $r, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $r->query('email')]);
    }

    public function reset(Request $r)
    {
        $r->validate(['token' => 'required|string', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(10)->mixedCase()->numbers()]]);
        $status = Password::reset($r->only('email', 'password', 'password_confirmation', 'token'), function (User $u, string $password) {
            $u->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            $u->tokens()->delete();
            event(new PasswordReset($u));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('login')->with('success', 'Password reset. Please sign in.');
    }
}
