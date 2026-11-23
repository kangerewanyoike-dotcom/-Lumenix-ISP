<?php

namespace App\Models\Concerns;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $builder->where($builder->getModel()->qualifyColumn('tenant_id'), app(TenantContext::class)->id());
        });

        static::creating(function (Model $model): void {
            $tenantId = app(TenantContext::class)->id();
            if ($model->tenant_id !== null && (string) $model->tenant_id !== $tenantId) {
                throw new \LogicException('A tenant model cannot be created for another tenant.');
            }
            $model->tenant_id = $tenantId;
        });
    }
}
