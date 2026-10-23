<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class VerificationController extends Controller
{
    public function notice(): View|RedirectResponse
    {
        return request()->user()->hasVerifiedEmail() ? redirect()->route('onboarding.show') : view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) return redirect()->route('dashboard');
        if ($request->user()->markEmailAsVerified()) event(new Verified($request->user()));
        return redirect()->route('onboarding.show')->with('status', 'email-verified');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) return redirect()->route('dashboard');
        try {
            $request->user()->sendEmailVerificationNotification();
            return back()->with('status', 'verification-link-sent');
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Verification email could not be resent.', ['user_id' => $request->user()->id, 'exception' => $exception::class]);
            return back()->with('status', 'verification-mail-failed');
        }
    }
}
