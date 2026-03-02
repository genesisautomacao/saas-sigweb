<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // O Bypass Definitivo (God Mode)
        Gate::before(function ($user, $ability) {

            // Fazemos uma consulta direta (RAW) no banco de dados.
            // Isso ignora completamente o cache do Spatie e a perda de contexto do Livewire.
            $hasSuperPower = DB::table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_id', $user->id)
                ->where('model_has_roles.model_type', get_class($user))
                ->whereIn('roles.name', ['Master', 'Manager'])
                ->exists();

            if ($hasSuperPower) {
                return true;
            }

            return null;
        });

        \Filament\Support\Facades\FilamentIcon::register([
            'panels::sidebar.collapse-button' => 'heroicon-o-bars-3',
            'panels::sidebar.expand-button' => 'heroicon-o-bars-3',
        ]);
    }
}