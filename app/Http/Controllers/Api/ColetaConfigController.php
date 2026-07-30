<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Coleta\ColetaConfigService;
use Illuminate\Http\Request;

/**
 * R67-3 — configuração do boletim de coleta para o app.
 * O app monta o formulário inteiro a partir deste payload: campos base exigidos,
 * campos padrão com o rótulo/lista do município, campos customizados e a região do
 * cadastrador. Mudar um vocabulário no painel NÃO exige nova versão do app.
 */
class ColetaConfigController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $tenant = $user->tenants()->first();

        if (! $tenant) {
            return response()->json(['message' => 'Usuário sem prefeitura vinculada.'], 403);
        }

        return response()->json(ColetaConfigService::config($tenant, $user));
    }
}
