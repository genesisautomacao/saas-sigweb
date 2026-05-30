<?php

namespace App\Http\Controllers;

use App\Models\ViabilidadeEmissao;

class ValidarViabilidadeController extends Controller
{
    public function show(string $protocolo)
    {
        $emissao = ViabilidadeEmissao::query()
            ->withoutGlobalScopes()
            ->with(['tenant', 'emissor'])
            ->where('protocolo', $protocolo)
            ->first();

        return view('validar-viabilidade', [
            'emissao' => $emissao,
        ]);
    }
}
