<?php

namespace App\Services\ApiTools;

use Illuminate\Support\Facades\File;
use App\Models\Tenant; // Não esqueça de importar o model do Tenant

class IntegraPrefeituraService
{
    /**
     * Simula a busca de um imóvel na API da Prefeitura.
     * Na fase de PoC, lê o arquivo JSON local com base no slug do Tenant.
     */
    public function buscarImovelPorCodigo(string $codigoTributario, $tenantId = null): ?array
    {
        // 1. Tenta descobrir o Tenant ID pelo Filament caso não tenha sido passado por parâmetro
        if (!$tenantId && class_exists('\Filament\Facades\Filament') && \Filament\Facades\Filament::getTenant()) {
            $tenantId = \Filament\Facades\Filament::getTenant()->id;
        }

        if (!$tenantId) {
            return null; // Sai silenciosamente se não souber de qual prefeitura é
        }

        // 2. Busca o Tenant no banco para descobrir o Slug
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            return null;
        }

        // 3. Monta o caminho do arquivo dinamicamente baseado no slug (Ex: bom-principio.json)
        $caminhoMock = storage_path("app/mocks/{$tenant->slug}.json");
        
        // 4. Se o arquivo da prefeitura não existir, não quebra o sistema, apenas retorna nulo
        if (!File::exists($caminhoMock)) {
            return null; 
        }

        // 5. Faz a leitura do arquivo JSON específico daquela prefeitura
        $imoveisApi = json_decode(File::get($caminhoMock), true);
        
        // Retorna o array do imóvel ou null se não achar
        return collect($imoveisApi)->firstWhere('codigo_imovel_tributario', $codigoTributario);
    }
}