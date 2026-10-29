<?php

namespace App\Services;

use App\Models\TenantAuditLog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TenantAudit
{
    public function record(string $action, ?string $type = null, ?int $id = null, array $details = []): void
    {
        $tenant = app(TenantContext::class)->current();
        if (! $tenant) throw new \LogicException('Audit requires explicit tenant context.');
        $actor = Auth::guard('web')->id();
        if ($actor && ! $tenant->users()->whereKey($actor)->exists()) $actor = null;
        TenantAuditLog::create(['actor_user_id' => $actor, 'action' => $action, 'target_type' => $type, 'target_id' => $id, 'details' => $details]);
    }

    public function changed(Model $model, string $action): void
    {
        if ($model instanceof TenantAuditLog) return;
        if ((int) $model->tenant_id !== app(TenantContext::class)->id()) return;
        // Store field names only, never values, message bodies, credentials or tokens.
        $changed = $action === 'created' ? array_keys($model->getAttributes()) : array_keys($model->getChanges());
        $fields = array_values(array_diff($changed, ['id', 'tenant_id', 'created_at', 'updated_at']));
        if ($action === 'updated' && $fields === []) return;
        $this->record($action, class_basename($model), $model->getKey(), ['fields' => $fields]);
    }
}
