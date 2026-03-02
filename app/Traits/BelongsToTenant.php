<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Filament\Facades\Filament;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant()
    {
        // 1. O BLOQUEIO DE LEITURA (GLOBAL SCOPE)
        // Toda vez que o Laravel fizer um "SELECT" no banco, ele vai injetar essa regra silenciosamente.
        static::addGlobalScope('tenant', function (Builder $builder) {
            
            // Se estivermos rodando um comando no terminal (como o nosso Seeder), ignora o bloqueio
            if (app()->runningInConsole()) {
                return;
            }

            $tenant = Filament::getTenant();

            if ($tenant) {
                // Usa o nome da tabela (ex: leads.tenant_id) para evitar erros de ambiguidades em Joins
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenant->id);
            }
        });

        // 2. A INJEÇÃO DE ESCRITA (SAVING EVENT)
        // Toda vez que o Laravel fizer um "INSERT", ele injeta o ID da transportadora sozinho.
        static::creating(function ($model) {
            if (app()->runningInConsole()) {
                return;
            }

            $tenant = Filament::getTenant();
            
            // Se tiver tenant logado e o programador esqueceu de mandar o tenant_id, a gente salva pra ele
            if ($tenant && empty($model->tenant_id)) {
                $model->tenant_id = $tenant->id;
            }
        });
    }
}