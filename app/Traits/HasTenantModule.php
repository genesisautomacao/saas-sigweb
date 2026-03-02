<?php

namespace App\Traits;

trait HasTenantModule
{
    public static function canAccess(): bool
    {
        // 1. Pega a transportadora logada
        $tenant = \Filament\Facades\Filament::getTenant();

        if (!$tenant) {
            return false;
        }

        // 2. Verifica se o módulo está ativo (A variável $tenantModule será definida no Resource)
        $modules = $tenant->modules ?? [];
        $hasModule = in_array(static::$tenantModule ?? '', $modules);

        // 3. A MÁGICA QUE CORRIGE O BUG: 
        // Retorna TRUE apenas se tiver o módulo E se passar na verificação da Policy (parent)
        return $hasModule && parent::canAccess();
    }
}