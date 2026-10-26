<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TenantLogoStorage
{
    public function migrateAllLegacyFiles(): int
    {
        $legacyPaths = DB::table('settings')->where('key', 'company_logo')
            ->where('value', 'like', 'logos/%')->distinct()->pluck('value');
        $migrated = 0;
        foreach ($legacyPaths as $path) {
            if (preg_match('#^logos/tenant-(\d+)/[A-Za-z0-9._-]+\.(png|gif|jpe?g)$#i', $path, $matches)) {
                $migrated += $this->migrateTenantLegacyPath($path, (int) $matches[1]) !== null ? 1 : 0;
            } elseif (preg_match('#^logos/[A-Za-z0-9._-]+\.(png|gif|jpe?g)$#i', $path)) {
                $migrated += count($this->migrateLegacyPath($path));
            }
        }
        return $migrated;
    }

    public function store(UploadedFile $file, int $tenantId): string
    {
        $extension = match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            default => 'jpg',
        };
        $path = "tenants/{$tenantId}/logos/".Str::uuid().'.'.$extension;
        Storage::disk('tenant_private')->put($path, file_get_contents($file->getRealPath()));
        return $path;
    }

    public function contents(?string $path, int $tenantId): ?string
    {
        if (! $path) return null;

        $privatePrefix = "tenants/{$tenantId}/logos/";
        if (str_starts_with($path, $privatePrefix)) {
            if (! preg_match('#^tenants/'.preg_quote((string) $tenantId, '#').'/logos/[A-Za-z0-9._-]+$#', $path)) return null;
            $disk = Storage::disk('tenant_private');
            return $disk->exists($path) ? $disk->get($path) : null;
        }

        $tenantLegacyPrefix = "logos/tenant-{$tenantId}/";
        if (str_starts_with($path, $tenantLegacyPrefix)
            && preg_match('#^logos/tenant-'.preg_quote((string) $tenantId, '#').'/[A-Za-z0-9._-]+\.(png|gif|jpe?g)$#i', $path)) {
            return $this->migrateTenantLegacyPath($path, $tenantId);
        }

        if (! preg_match('#^logos/[A-Za-z0-9._-]+\.(png|gif|jpe?g)$#i', $path)) return null;
        return $this->migrateOneLegacyPath($path, $tenantId);
    }

    public function delete(?string $path, int $tenantId): void
    {
        if (! $path) return;
        if (str_starts_with($path, "tenants/{$tenantId}/logos/") && preg_match('#^tenants/'.preg_quote((string) $tenantId, '#').'/logos/[A-Za-z0-9._-]+$#', $path)) {
            Storage::disk('tenant_private')->delete($path);
            return;
        }
        if (str_starts_with($path, "logos/tenant-{$tenantId}/")
            && preg_match('#^logos/tenant-'.preg_quote((string) $tenantId, '#').'/[A-Za-z0-9._-]+\.(png|gif|jpe?g)$#i', $path)) {
            $stillReferenced = DB::table('settings')->where('key', 'company_logo')->where('value', $path)->where('tenant_id', '<>', $tenantId)->exists();
            if (! $stillReferenced) Storage::disk('public')->delete($path);
            return;
        }
        if (preg_match('#^logos/[A-Za-z0-9._-]+\.(png|gif|jpe?g)$#i', $path)) {
            $otherReferences = DB::table('settings')->where('key', 'company_logo')->where('value', $path)->where('tenant_id', '<>', $tenantId)->exists();
            if (! $otherReferences) Storage::disk('public')->delete($path);
        }
    }

    private function migrateLegacyPath(string $legacyPath): array
    {
        $public = Storage::disk('public');
        if (! $public->exists($legacyPath)) return [];

        $contents = $public->get($legacyPath);
        $extension = strtolower(pathinfo($legacyPath, PATHINFO_EXTENSION)) ?: 'jpg';
        $tenantIds = DB::table('settings')
            ->where('key', 'company_logo')->where('value', $legacyPath)
            ->pluck('tenant_id');
        $migrated = [];
        foreach ($tenantIds as $referencingTenantId) {
            $referencingTenantId = (int) $referencingTenantId;
            $newPath = "tenants/{$referencingTenantId}/logos/".Str::uuid().'.'.$extension;
            Storage::disk('tenant_private')->put($newPath, $contents);
            DB::table('settings')->where('tenant_id', $referencingTenantId)
                ->where('key', 'company_logo')->where('value', $legacyPath)->update(['value' => $newPath, 'updated_at' => now()]);
            DB::table('tenants')->where('id', $referencingTenantId)->update(['logo_path' => $newPath, 'updated_at' => now()]);
            Cache::forget("setting_{$referencingTenantId}_company_logo");
            $migrated[$referencingTenantId] = ['contents' => $contents, 'path' => $newPath];
        }
        $public->delete($legacyPath);
        return $migrated;
    }

    private function migrateOneLegacyPath(string $legacyPath, int $tenantId): ?string
    {
        $public = Storage::disk('public');
        if (! $public->exists($legacyPath)) return null;
        $setting = DB::table('settings')->where('tenant_id', $tenantId)->where('key', 'company_logo')->where('value', $legacyPath)->first();
        if (! $setting) return null;

        $contents = $public->get($legacyPath);
        $extension = strtolower(pathinfo($legacyPath, PATHINFO_EXTENSION)) ?: 'jpg';
        $newPath = "tenants/{$tenantId}/logos/".Str::uuid().'.'.$extension;
        Storage::disk('tenant_private')->put($newPath, $contents);
        DB::table('settings')->where('id', $setting->id)->where('tenant_id', $tenantId)
            ->update(['value' => $newPath, 'updated_at' => now()]);
        DB::table('tenants')->where('id', $tenantId)->update(['logo_path' => $newPath, 'updated_at' => now()]);
        Cache::forget("setting_{$tenantId}_company_logo");

        $otherReferences = DB::table('settings')->where('key', 'company_logo')->where('value', $legacyPath)->where('tenant_id', '<>', $tenantId)->exists();
        if (! $otherReferences) $public->delete($legacyPath);
        return $contents;
    }

    private function migrateTenantLegacyPath(string $legacyPath, int $tenantId): ?string
    {
        $public = Storage::disk('public');
        if (! $public->exists($legacyPath)) return null;
        $contents = $public->get($legacyPath);
        $extension = strtolower(pathinfo($legacyPath, PATHINFO_EXTENSION)) ?: 'jpg';
        $newPath = "tenants/{$tenantId}/logos/".Str::uuid().'.'.$extension;
        Storage::disk('tenant_private')->put($newPath, $contents);
        DB::table('settings')->where('tenant_id', $tenantId)->where('key', 'company_logo')->where('value', $legacyPath)
            ->update(['value' => $newPath, 'updated_at' => now()]);
        DB::table('tenants')->where('id', $tenantId)->update(['logo_path' => $newPath, 'updated_at' => now()]);
        Cache::forget("setting_{$tenantId}_company_logo");
        $public->delete($legacyPath);
        return $contents;
    }

}
