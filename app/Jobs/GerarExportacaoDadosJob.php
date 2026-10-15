<?php

namespace App\Jobs;

use App\Models\ArquivoGerado;
use App\Services\Exports\ExportadorDadosService;

/**
 * R84-7 — "Exportar dados" na fila. Parâmetros (ArquivoGerado::parametros):
 *  - camadas: chaves de ExportadorDadosService::CAMADAS
 *  - formato: gpkg | json | xlsx
 *  - recorte: ['tipo' => 'nenhum'] | ['tipo' => 'bairros', 'bairro_ids' => [...]]
 *             | ['tipo' => 'ids', 'camada' => 'lotes', 'ids' => [...]] (resultado do filtro do mapa)
 */
class GerarExportacaoDadosJob extends GerarArquivoJob
{
    protected function gerar(ArquivoGerado $arquivo, string $dirTrabalho): array
    {
        $p = $arquivo->parametros ?? [];
        $exportador = new ExportadorDadosService($arquivo->tenant, $p['recorte'] ?? []);

        return $exportador->gerar(
            $p['camadas'] ?? [],
            $p['formato'] ?? 'gpkg',
            $dirTrabalho,
            fn (int $pct) => $this->progresso($arquivo, $pct),
        );
    }
}
