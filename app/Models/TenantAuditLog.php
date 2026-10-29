<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TenantAuditLog extends Model
{
    use BelongsToTenant;
    public $timestamps = false;
    protected $guarded = ['id', 'tenant_id'];
    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created' => 'Record created',
            'updated' => 'Record updated',
            'deleted' => 'Record deleted',
            'workspace.profile_updated' => 'Workspace profile updated',
            'workspace.logo_updated' => 'Workspace logo updated',
            'workspace.deleted' => 'Workspace deletion requested',
            'invoice.lines_replaced' => 'Invoice lines replaced',
            'invitation.created' => 'Invitation created',
            'invitation.resent' => 'Invitation reissued',
            'membership.invitation_accepted' => 'Invitation accepted',
            'membership.removed' => 'Member removed',
            'membership.role_changed' => 'Member role changed',
            default => ucfirst(str_replace(['.', '_'], ' ', $this->action)),
        };
    }

    public function getDetailsSummaryAttribute(): string
    {
        $details = $this->details ?? [];
        if (isset($details['fields']) && is_array($details['fields'])) {
            return implode(', ', array_filter($details['fields'], 'is_string'));
        }
        if (isset($details['removed_count'], $details['replacement_count'])
            && is_numeric($details['removed_count']) && is_numeric($details['replacement_count'])) {
            return (int) $details['removed_count'].' removed; '.(int) $details['replacement_count'].' added';
        }
        if ($this->action === 'workspace.logo_updated' && in_array($details['operation'] ?? null, ['uploaded', 'removed'], true)) {
            return 'Logo '.$details['operation'];
        }
        return '';
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit records are append-only.'));
    }
}
