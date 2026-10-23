<?php

namespace App\Support;

use App\Models\Tenant;

class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function tenant(): ?\App\Models\Tenant
    {
        return $this->tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
