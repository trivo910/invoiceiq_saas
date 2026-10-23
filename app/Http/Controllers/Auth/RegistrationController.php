<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\WorkspaceRegistrationService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(): View { return view('auth.register'); }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'workspace' => ['required', 'string', 'max:255'],
        ]);

        $user = app(WorkspaceRegistrationService::class)->register($data);

        auth()->login($user);
        $request->session()->regenerate();
        $request->session()->put('current_tenant_id', $user->tenants()->orderBy('tenants.id')->value('tenants.id'));
        try {
            $user->sendEmailVerificationNotification();
            return redirect()->route('verification.notice')->with('status', 'verification-link-sent');
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Verification email could not be sent during registration.', ['user_id' => $user->id, 'exception' => $exception::class]);
            return redirect()->route('verification.notice')->with('status', 'verification-mail-failed');
        }
    }
}
