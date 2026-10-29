<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use LogicException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(fn ($model) => app(\App\Services\TenantAudit::class)->changed($model, $event));
        }
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = App::make(TenantContext::class)->id();

            if ($tenantId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->where($builder->getModel()->qualifyColumn('tenant_id'), $tenantId);
        });

        static::creating(function ($model): void {
            $tenantId = App::make(TenantContext::class)->id();

            if ($tenantId === null) {
                throw new LogicException('A tenant context is required to create '.class_basename($model).'.');
            }

            $model->setAttribute('tenant_id', $tenantId);
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
