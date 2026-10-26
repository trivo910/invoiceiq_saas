<?php

namespace App\Http\Controllers;

use App\Models\TenantInvitation;
use App\Models\Tenant;
use App\Services\BillingService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class UsersController extends Controller
{
    public function index(): View
    {
        $tenant = app(TenantContext::class)->tenant();
        return view('users.index', ['users' => $tenant->users()->orderBy('name')->get(), 'invitations' => TenantInvitation::where('tenant_id', $tenant->id)->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'role' => ['required', 'in:admin,accountant,staff,viewer']]);
        $tenant = app(TenantContext::class)->tenant();
        if ($tenant->users()->where('email', $data['email'])->exists()) return back()->withErrors(['email' => 'This person is already a workspace member.']);
        $plainToken = Str::random(64);
        $invite = null;
        $limitReached = false;
        \Illuminate\Support\Facades\DB::transaction(function () use ($tenant, $data, $request, $plainToken, &$invite, &$limitReached) {
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $existing = TenantInvitation::where('tenant_id', $tenant->id)->where('email', $data['email'])->first();
            if (app(BillingService::class)->limitReached($tenant, 'members', $existing?->id)) {
                $limitReached = true;
                return;
            }
            $invite = TenantInvitation::updateOrCreate(['tenant_id' => $tenant->id, 'email' => $data['email']], ['invited_by' => $request->user()->id, 'role' => $data['role'], 'token_hash' => hash('sha256', $plainToken), 'expires_at' => now()->addDays(7), 'accepted_at' => null]);
        });
        if ($limitReached) return redirect()->route('billing.index')->with('billing_limit', 'Member limit reached for your plan.');
        $url = route('invitations.accept', $plainToken);
        try {
            Mail::raw("You have been invited to join {$tenant->name} on InvoiceIQ. Accept your invitation within 7 days: {$url}", fn ($message) => $message->to($invite->email)->subject("Invitation to {$tenant->name}"));
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Workspace invitation email could not be sent.', ['invitation_id' => $invite->id, 'exception' => $exception::class]);
            return redirect()->route('users.index')->with('error', 'Invitation saved, but email delivery failed. Configure SMTP or the local log mailer, then resend.');
        }
        return redirect()->route('users.index')->with('success', 'Invitation sent.');
    }

    public function destroy(int $user): RedirectResponse
    {
        $tenant = app(TenantContext::class)->tenant();
        abort_if($user === auth()->id(), 422, 'You cannot remove your own account.');
        abort_unless($tenant->users()->whereKey($user)->exists(), 404);
        abort_if($tenant->users()->wherePivot('role', 'owner')->count() <= 1 && $tenant->users()->whereKey($user)->wherePivot('role', 'owner')->exists(), 422, 'The workspace must keep an owner.');
        $tenant->users()->detach($user);
        return redirect()->route('users.index')->with('success', 'Member removed from this workspace.');
    }

    public function updateRole(Request $request, int $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', 'in:admin,accountant,staff,viewer']]);
        $tenant = app(TenantContext::class)->tenant();
        abort_unless($tenant->users()->whereKey($user)->exists(), 404);
        abort_if($user === auth()->id() || $tenant->users()->whereKey($user)->wherePivot('role', 'owner')->exists(), 422, 'Owner role cannot be changed here.');
        $tenant->users()->updateExistingPivot($user, ['role' => $data['role']]);
        return redirect()->route('users.index')->with('success', 'Workspace role updated.');
    }

}
