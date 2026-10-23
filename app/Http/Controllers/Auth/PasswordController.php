<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function requestForm(): View { return view('auth.forgot-password'); }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Password reset email could not be sent.', ['exception' => $exception::class]);
            return back()->with('error', 'Email delivery is unavailable. Configure SMTP or use the local log mailer, then try again.');
        }
        return back()->with($status === Password::RESET_LINK_SENT ? 'status' : 'error', __($status));
    }

    public function resetForm(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]])->onlyInput('email');
    }
}
