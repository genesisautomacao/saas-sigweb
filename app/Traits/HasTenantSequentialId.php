<?php

namespace App\Traits;

use Filament\Facades\Filament;

trait HasTenantSequentialId
{
    protected static function bootHasTenantSequentialId()
    {
        static::creating(function ($model) {

            // 1. GARANTIA DE TENANT: Puxa o Tenant antes de qualquer coisa (Igual ao Organósi)
            $tenantId = $model->tenant_id ?? Filament::getTenant()?->id;

            if (is_null($model->sequential_id) && $tenantId) {

                // 2. O PULO DO GATO: withoutGlobalScopes()
                // Ignora o SoftDeletes para não reutilizar números de Leads apagados
                $maxId = $model->newQuery()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate() // 3. ANTI-COLISÃO: Trava a tabela em importações simultâneas
                    ->max('sequential_id');

                $model->sequential_id = ($maxId ?? 0) + 1;

                // Preenche o tenant_id no model por segurança extra
                $model->tenant_id = $tenantId;
            }
        });
    }
}