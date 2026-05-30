<?php

namespace App\Services\Exports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Exporta uma camada GIS do tenant como arquivo Shapefile (.zip).
 *
 * TR Tangará Intranet #30 — "Exportação de camada selecionada pelo usuário para o formato SHP".
 *
 * Dependência: `ogr2ogr` (GDAL) precisa estar instalado e no PATH do servidor.
 *  - Linux:   `apt-get install gdal-bin`
 *  - Windows: GDAL via OSGeo4W ou Conda
 */
class ShapefileExportService
{
    /**
     * Lista branca de camadas exportáveis (mesmo padrão do MapDataController).
     */
    public const ALLOWED_LAYERS = [
        'lotes', 'edificacoes', 'logradouros', 'quadras', 'bairros',
        'loteamentos', 'zonas', 'perimetros_urbanos',
        'arvores', 'postes', 'cemiterios',
        'rural_propriedades', 'rural_estradas', 'rural_pontes',
        'rural_localidades', 'rural_hidrografias',
        'setores_fiscais',
    ];

    public function export(string $layer, int $tenantId)
    {
        if (!in_array($layer, self::ALLOWED_LAYERS, true)) {
            abort(403, 'Camada não permitida para exportação.');
        }

        // 1. Pré-flight: ogr2ogr está disponível?
        if (!$this->ogr2ogrDisponivel()) {
            abort(500, 'GDAL (ogr2ogr) não está instalado no servidor. Solicite ao administrador a instalação para habilitar a exportação SHP.');
        }

        // 2. Coleta features do PostGIS já como FeatureCollection GeoJSON
        $geojson = $this->buildGeoJSON($layer, $tenantId);

        if ($geojson['features'] === []) {
            abort(404, 'A camada selecionada não tem registros para exportar.');
        }

        // 3. Diretório temporário isolado
        $base = storage_path('app/tmp/shp-' . uniqid('', true));
        File::makeDirectory($base, 0755, true);

        $jsonPath = $base . '/' . $layer . '.geojson';
        File::put($jsonPath, json_encode($geojson));

        // 4. Roda ogr2ogr — Shapefile tem limites (nome de coluna ≤10 chars; encoding UTF-8 via .cpg)
        $shpDir = $base . '/shp';
        File::makeDirectory($shpDir, 0755, true);

        $process = new Process([
            'ogr2ogr',
            '-f', 'ESRI Shapefile',
            '-lco', 'ENCODING=UTF-8',
            '-nlt', 'PROMOTE_TO_MULTI', // converte Polygon → MultiPolygon (Shapefile não suporta mistura)
            $shpDir . '/' . $layer . '.shp',
            $jsonPath,
        ]);
        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            $erro = $process->getErrorOutput() ?: $process->getOutput();
            File::deleteDirectory($base);
            abort(500, 'Falha na conversão SHP: ' . trim($erro));
        }

        // 5. Zipa o diretório shp/
        $zipPath = $base . '/' . $layer . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            File::deleteDirectory($base);
            abort(500, 'Não foi possível criar o arquivo .zip de saída.');
        }
        foreach (File::files($shpDir) as $f) {
            $zip->addFile($f->getPathname(), $f->getFilename());
        }
        $zip->close();

        // 6. Stream do download + cleanup
        return response()->download($zipPath, $layer . '-' . date('Ymd-His') . '.zip')
            ->deleteFileAfterSend(true)
            ->headers(['X-Accel-Buffering' => 'no'])
            ->prepare(request())
            ->sendHeaders();
    }

    /**
     * Alternativa stream-friendly: gera o ZIP e retorna como streamDownload (com cleanup do diretório).
     */
    public function exportStream(string $layer, int $tenantId)
    {
        if (!in_array($layer, self::ALLOWED_LAYERS, true)) {
            abort(403, 'Camada não permitida para exportação.');
        }

        if (!$this->ogr2ogrDisponivel()) {
            abort(500, 'GDAL (ogr2ogr) não está instalado no servidor. Solicite ao administrador a instalação para habilitar a exportação SHP.');
        }

        $geojson = $this->buildGeoJSON($layer, $tenantId);
        if ($geojson['features'] === []) {
            abort(404, 'A camada selecionada não tem registros para exportar.');
        }

        $base = storage_path('app/tmp/shp-' . uniqid('', true));
        File::makeDirectory($base, 0755, true);

        $jsonPath = $base . '/' . $layer . '.geojson';
        File::put($jsonPath, json_encode($geojson));

        $shpDir = $base . '/shp';
        File::makeDirectory($shpDir, 0755, true);

        $process = new Process([
            'ogr2ogr',
            '-f', 'ESRI Shapefile',
            '-lco', 'ENCODING=UTF-8',
            '-nlt', 'PROMOTE_TO_MULTI',
            $shpDir . '/' . $layer . '.shp',
            $jsonPath,
        ]);
        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            $erro = $process->getErrorOutput() ?: $process->getOutput();
            File::deleteDirectory($base);
            abort(500, 'Falha na conversão SHP: ' . trim($erro));
        }

        $zipPath = $base . '/' . $layer . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        foreach (File::files($shpDir) as $f) {
            $zip->addFile($f->getPathname(), $f->getFilename());
        }
        $zip->close();

        $fileName = $layer . '-' . date('Ymd-His') . '.zip';
        $size = filesize($zipPath);
        $content = file_get_contents($zipPath);
        File::deleteDirectory($base);

        return response($content, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Content-Length'      => (string) $size,
        ]);
    }

    protected function ogr2ogrDisponivel(): bool
    {
        $cmd = PHP_OS_FAMILY === 'Windows' ? 'where ogr2ogr' : 'which ogr2ogr';
        $process = Process::fromShellCommandline($cmd);
        $process->run();
        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }

    /**
     * Monta uma FeatureCollection GeoJSON da camada, escopada pelo tenant.
     * Filtra colunas geográficas/binárias do select para não estourar o GeoJSON com lixo.
     */
    protected function buildGeoJSON(string $layer, int $tenantId): array
    {
        // Descobre as colunas escalares (todas exceto a geo)
        $cols = DB::getSchemaBuilder()->getColumnListing($layer);
        $attrCols = array_filter($cols, fn ($c) => !in_array($c, ['geo', 'foto_frontal', 'foto_lateral_esq', 'foto_lateral_dir'], true));

        $selectAttrs = implode(', ', array_map(fn ($c) => "\"$c\"", $attrCols));

        $rows = DB::select("
            SELECT
                {$selectAttrs},
                ST_AsGeoJSON(geo) AS geo_json
            FROM {$layer}
            WHERE tenant_id = ?
              AND deleted_at IS NULL
              AND geo IS NOT NULL
        ", [$tenantId]);

        $features = [];
        foreach ($rows as $row) {
            $rowArray = (array) $row;
            $geom = json_decode($rowArray['geo_json'], true);
            unset($rowArray['geo_json']);

            // Trunca campos JSON longos (dados_tributarios) — Shapefile DBF tem limite de 254 chars/coluna
            foreach ($rowArray as $k => $v) {
                if (is_string($v) && strlen($v) > 254) {
                    $rowArray[$k] = substr($v, 0, 251) . '...';
                }
            }

            $features[] = [
                'type'       => 'Feature',
                'geometry'   => $geom,
                'properties' => $rowArray,
            ];
        }

        return [
            'type'     => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
