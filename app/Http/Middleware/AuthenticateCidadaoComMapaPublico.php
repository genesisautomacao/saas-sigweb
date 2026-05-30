<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Request;

/**
 * Authenticate do painel Cidadão com bypass para o Mapa Público.
 *
 * Item TR Tangará: acesso público (sem cadastro) ao mapa cidadão.
 * Demais páginas do painel continuam exigindo login.
 */
class AuthenticateCidadaoComMapaPublico extends Authenticate
{
    public function handle($request, Closure $next, ...$guards): mixed
    {
        $path = $request->path();

        // Libera o acesso anônimo ao mapa cidadão (e às rotas auxiliares Livewire).
        if (
            str_contains($path, 'mapa-publico') ||
            str_contains($path, 'livewire')
        ) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$guards);
    }
}
