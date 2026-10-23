<?php

namespace App\Http\Controllers;

use App\Models\TenantInvitation;
use App\Models\User;
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
        } else {
            abort_if(User::where('email', $invite->email)->exists(), 403, 'Please sign in before accepting this workspace invitation.');
            $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
            $user = User::create(['name' => $data['name'], 'email' => $invite->email, 'password' => Hash::make($data['password']), 'role' => 'staff']);
            $user->markEmailAsVerified();
        }
        DB::transaction(function () use ($invite, $user) {
            $invite->tenant->users()->syncWithoutDetaching([$user->id => ['role' => $invite->role]]);
            $invite->update(['accepted_at' => now()]);
        });
        auth()->login($user);
        request()->session()->regenerate();
        request()->session()->put('current_tenant_id', $invite->tenant_id);
        return redirect()->route('dashboard')->with('success', 'You joined '.$invite->tenant->name.'.');
    }

    private function find(string $token): ?TenantInvitation
    {
        return TenantInvitation::with('tenant')->where('token_hash', hash('sha256', $token))->first();
    }
}
