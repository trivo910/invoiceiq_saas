<?php

namespace App\Http\Controllers;

use App\Models\TenantInvitation;
use App\Models\User;
use App\Models\Tenant;
use App\Services\BillingService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $invite = $this->find($token);
        abort_unless($invite && $invite->expires_at->isFuture(), 404, 'This invitation is invalid or expired.');
        abort_if($invite->accepted_at, 410, 'This invitation has already been accepted.');
        abort_if(auth()->check() && auth()->user()->email !== $invite->email, 403, 'Sign in with the invited email address.');
        if (! auth()->check() && User::where('email', $invite->email)->exists()) {
            return redirect()->guest(route('login'));
        }
        return view('auth.accept-invitation', compact('invite', 'token'));
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invite = $this->find($token);
        abort_unless($invite && $invite->expires_at->isFuture() && ! $invite->accepted_at, 404);
        if (auth()->check()) {
            abort_unless(auth()->user()->email === $invite->email, 403);
            $user = auth()->user();
            $data = null;
        } else {
            abort_if(User::where('email', $invite->email)->exists(), 403, 'Please sign in before accepting this workspace invitation.');
            $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
            $user = null;
        }
        $context = app(TenantContext::class);
        $tenant = $invite->tenant;
        $context->set($tenant);
        try {
            DB::transaction(function () use ($invite, $tenant, &$user, $data, $context) {
                Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                $lockedInvite = TenantInvitation::whereKey($invite->id)->lockForUpdate()->firstOrFail();
                abort_unless(! $lockedInvite->accepted_at && $lockedInvite->expires_at->isFuture(), 410);
                abort_unless(app(BillingService::class)->canWrite($tenant), 403, 'This workspace cannot accept members while it is read-only.');
                abort_unless(! app(BillingService::class)->limitReached($tenant, 'members', $lockedInvite->id), 403, 'This workspace has reached its member limit.');
                if (! $user) {
                    $user = User::create(['name' => $data['name'], 'email' => $lockedInvite->email, 'password' => Hash::make($data['password']), 'role' => 'staff']);
                    $user->markEmailAsVerified();
                }
                $tenant->users()->syncWithoutDetaching([$user->id => ['role' => $lockedInvite->role]]);
                app(\App\Services\TenantAudit::class)->record('membership.invitation_accepted', 'User', $user->id, ['fields' => ['membership']]);
                $lockedInvite->update(['accepted_at' => now()]);
            });
        } finally {
            $context->clear();
        }
        auth()->login($user);
        request()->session()->regenerate();
        request()->session()->put('current_tenant_id', $invite->tenant_id);
        return redirect()->route('dashboard')->with('success', 'You joined '.$invite->tenant->name.'.');
    }

    private function find(string $token): ?TenantInvitation
    {
        return TenantInvitation::with('tenant')->whereHas('tenant', fn ($q) => $q->where('status', 'active'))->where('token_hash', hash('sha256', $token))->first();
    }
}
