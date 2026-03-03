<?php

namespace App\Traits;

use Filament\Facades\Filament;

trait HasTenantSequentialId
{
    protected static function bootHasTenantSequentialId()
    {
        static::creating(function ($model) {

            $tenantId = $model->tenant_id ?? Filament::getTenant()?->id;

            if (empty($model->sequential_id) && $tenantId) {

                // Compatibilidade com PostgreSQL: Ordena DESC e pega o primeiro com Lock
                $lastRecord = $model->newQuery()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->orderByDesc('sequential_id')
                    ->lockForUpdate()
                    ->first();

                $maxId = $lastRecord ? $lastRecord->sequential_id : 0;

                $model->sequential_id = $maxId + 1;
                $model->tenant_id = $tenantId;
            }
        });
    }
}