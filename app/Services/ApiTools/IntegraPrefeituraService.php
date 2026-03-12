<?php

namespace App\Services\ApiTools;

use Illuminate\Support\Facades\File;

class IntegraPrefeituraService
{
    /**
     * Simula a busca de um imóvel na API da Prefeitura.
     * Na fase de PoC, lê o arquivo JSON local.
     */
    public function buscarImovelPorCodigo(string $codigoTributario): ?array
    {
        $caminhoMock = storage_path('app/mocks/imoveis_santacecilia.json');
        
        if (!File::exists($caminhoMock)) {
            throw new \Exception("Arquivo de simulação da API não encontrado.");
        }

        $imoveisApi = json_decode(File::get($caminhoMock), true);
        
        // Retorna o array do imóvel ou null se não achar
        return collect($imoveisApi)->firstWhere('codigo_imovel_tributario', $codigoTributario);
    }
}