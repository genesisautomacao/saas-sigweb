<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncSpatieTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // Se houver uma Tenant ativa no painel
        if ($tenant = Filament::getTenant()) {

            // 1. Avisa ao Spatie que as permissões e papéis devem usar esse ID
            setPermissionsTeamId($tenant->id);

            // 2. Pega o usuário logado usando a Facade oficial do Filament
            $user = Filament::auth()->user();

            // 3. Verifica se existe um usuário e se ele é um Model do Eloquent 
            // (Isso resolve o erro do unsetRelation)
            if ($user instanceof \Illuminate\Database\Eloquent\Model) {
                $user->unsetRelation('roles');
                $user->unsetRelation('permissions');
            }
        }

        return $next($request);
    }
}