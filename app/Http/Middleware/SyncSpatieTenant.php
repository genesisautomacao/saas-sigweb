<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Colors\Color;

class SyncSpatieTenant
{

    public function handle(Request $request, Closure $next)
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            // 1. Sincroniza as permissões do Spatie (Provavelmente você já tem essa linha)
            setPermissionsTeamId($tenant->id);

            // 2. Pega o usuário logado usando a Facade oficial do Filament
            $user = Filament::auth()->user();

            // 3. Verifica se existe um usuário e se ele é um Model do Eloquent 
            // (Isso resolve o erro do unsetRelation)
            if ($user instanceof \Illuminate\Database\Eloquent\Model) {
                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');
            }

            // 2. A MÁGICA DA COR DINÂMICA
            // Pega a cor do banco, se não existir, usa o azul padrão do Filament (#3b82f6)
            $hexColor = data_get($tenant->data, 'color', '#3b82f6');

            if ($hexColor) {
                // O FilamentColor::register reescreve a cor 'primary' na hora, gerando todos os tons
                FilamentColor::register([
                    'primary' => Color::hex($hexColor),
                ]);
            }
        }

        return $next($request);
    }
}