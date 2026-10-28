<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WorkspaceRegistrationService
{
    public function __construct(private TenantContext $context)
    {
    }

    public function register(array $data): User
    {
        $trialDays = filter_var(config('billing.trial_days', 14), FILTER_VALIDATE_INT);
        if ($trialDays === false || $trialDays < 0) {
            throw new InvalidArgumentException('BILLING_TRIAL_DAYS must be a non-negative integer.');
        }

        return DB::transaction(function () use ($data, $trialDays) {
            $user = User::create([
                'name' => $data['name'], 'email' => $data['email'],
                'password' => Hash::make($data['password']), 'role' => 'admin',
            ]);
            $tenant = Tenant::create([
                'name' => $data['workspace'],
                'slug' => Str::slug($data['workspace']).'-'.Str::lower(Str::random(6)),
                'status' => 'active',
                'trial_ends_at' => now()->addDays($trialDays),
            ]);
            $tenant->users()->attach($user->id, ['role' => 'owner']);
            $trial = \App\Models\Plan::where('code', 'trial')->firstOrFail();
            $this->context->set($tenant);
            try {
                $tenant->subscriptions()->create([
                    'plan_id' => $trial->id,
                    'status' => 'trialing',
                    'trial_ends_at' => $tenant->trial_ends_at,
                ]);
            } finally {
                $this->context->clear();
            }
            return $user;
        });
    }
}
