<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Filament\Facades\Filament;
use App\Models\Tenant; // <-- ADICIONE ISTO

trait BelongsToTenant
{
    protected static function bootBelongsToTenant()
    {
        // 1. O BLOQUEIO DE LEITURA (GLOBAL SCOPE)
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (app()->runningInConsole()) {
                return;
            }

            $tenant = Filament::getTenant();

            if ($tenant) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenant->id);
            }
        });

        // 2. A INJEÇÃO DE ESCRITA (SAVING EVENT)
        static::creating(function ($model) {
            if (app()->runningInConsole()) {
                return;
            }

            $tenant = Filament::getTenant();
            
            if ($tenant && empty($model->tenant_id)) {
                $model->tenant_id = $tenant->id;
            }
        });
    }

    // --- A SOLUÇÃO DO FILAMENT AQUI ---
    /**
     * Define o relacionamento com o Tenant para o Filament reconhecer automaticamente.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}