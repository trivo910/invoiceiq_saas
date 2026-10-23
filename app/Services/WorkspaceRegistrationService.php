<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WorkspaceRegistrationService
{
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'], 'email' => $data['email'],
                'password' => Hash::make($data['password']), 'role' => 'admin',
            ]);
            $tenant = Tenant::create([
                'name' => $data['workspace'],
                'slug' => Str::slug($data['workspace']).'-'.Str::lower(Str::random(6)),
                'status' => 'active',
            ]);
            $tenant->users()->attach($user->id, ['role' => 'owner']);
            return $user;
        });
    }
}
