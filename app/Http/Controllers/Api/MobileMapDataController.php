<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileMapDataController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'layer' => 'required|string',
            'bbox' => 'nullable|string',
        ]);

        $tenant = $request->user()->tenants()->first();
        if (! $tenant) {
            return response()->json(['error' => 'Usuário sem tenant ativo.'], 403);
        }
        $tenantId = $tenant->id;
        $layer = $request->query('layer');
        $bbox = $this->parseBbox($request->query('bbox'));

        $result = $this->buildLayerQuery($layer, $tenantId, $bbox);

        if ($result === null) {
            return response()->json(['error' => 'Camada não encontrada'], 404);
        }

        return response()->json($result);
    }

    /**
     * GET /api/map/layers — catálogo de camadas que o app pode exibir (item 179).
     *
     * Retorna só as camadas "configuradas no SIG WEB" para este tenant:
     *   1) as camadas base + as dos módulos ativos do tenant;
     *   2) se o admin curou uma lista em tenant.data['mobile_layers'], restringe a ela.
     * Cada camada vem com metadados de exibição (label, tipo, cor, visível por padrão,
     * zoom mínimo) — o app monta o seletor e o estilo direto disto, sem hardcode.
     */
    public function layers(Request $request)
    {
        $tenant = $request->user()->tenants()->first();
        $modules = $tenant?->modules ?? [];

        // Camadas servidas por /api/map/data. 'modulo' = null → base (sempre disponível).
        // O app de coleta cadastral trata apenas do cadastro imobiliário (lote, edificação
        // e unidade), então o catálogo é só a base cartográfica de apoio — árvores e postes
        // saíram junto com os syncs de arborização/manutenção.
        $catalogo = [
            ['key' => 'lotes',       'label' => 'Lotes',       'tipo' => 'polygon', 'cor' => '#3388ff', 'modulo' => null, 'visivel_padrao' => true,  'min_zoom' => 15],
            ['key' => 'quadras',     'label' => 'Quadras',     'tipo' => 'polygon', 'cor' => '#ff7800', 'modulo' => null, 'visivel_padrao' => false, 'min_zoom' => 14],
            ['key' => 'bairros',     'label' => 'Bairros',     'tipo' => 'polygon', 'cor' => '#8e44ad', 'modulo' => null, 'visivel_padrao' => false, 'min_zoom' => 12],
            ['key' => 'logradouros', 'label' => 'Logradouros', 'tipo' => 'line',    'cor' => '#7f8c8d', 'modulo' => null, 'visivel_padrao' => false, 'min_zoom' => 15],
            ['key' => 'zonas',       'label' => 'Zonas',       'tipo' => 'polygon', 'cor' => '#2ecc71', 'modulo' => null, 'visivel_padrao' => false, 'min_zoom' => 13],
        ];

        // 1) só camadas base ou de módulo ativo
        $disponiveis = array_filter($catalogo, fn ($c) => $c['modulo'] === null || in_array($c['modulo'], $modules));

        // 2) curadoria opcional do admin (SIG WEB → tenant.data['mobile_layers'])
        $curadoria = data_get($tenant?->data, 'mobile_layers');
        if (is_array($curadoria) && count($curadoria)) {
            $disponiveis = array_filter($disponiveis, fn ($c) => in_array($c['key'], $curadoria));
        }

        return response()->json(['data' => array_values($disponiveis)]);
    }

    private function parseBbox(?string $raw): ?array
    {
        if (! $raw) {
            return null;
        }
        $parts = array_map('floatval', explode(',', $raw));

        return count($parts) === 4 ? $parts : null;
    }

    private function buildLayerQuery(string $layer, int $tenantId, ?array $bbox): ?array
    {
        switch ($layer) {
            case 'lotes':
                return $this->layerLotes($tenantId, $bbox);

            case 'quadras':
                return $this->layerSimples('quadras', 'name', $tenantId, $bbox);

            case 'logradouros':
                return $this->layerSimples('logradouros', 'name', $tenantId, $bbox);

            case 'bairros':
                return $this->layerSimples('bairros', 'name', $tenantId, $bbox);

            case 'zonas':
                return $this->layerSimples('zonas', 'sigla', $tenantId, $bbox);

            default:
                return null;
        }
    }

    private function layerLotes(int $tenantId, ?array $bbox): array
    {
        $q = DB::table('lotes')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('geo')
            ->selectRaw('id, code, numero_lote, sequential_id, status_cadastro, ocupacao, ST_AsGeoJSON(geo, 6) as geo_json');

        $this->applyBbox($q, 'geo', $bbox);

        $features = [];
        foreach ($q->get() as $row) {
            $geom = json_decode($row->geo_json);
            if (! $geom || empty($geom->coordinates)) {
                continue;
            }
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'id' => $row->id,
                    'name' => $row->numero_lote ?? 'S/N',
                    'codigo' => $row->code,
                    'sequential_id' => $row->sequential_id,
                    'status_cadastro' => $row->status_cadastro ?? 'nao_visitado',
                    'ocupacao' => $row->ocupacao,
                    'layer' => 'lotes',
                ],
                'geometry' => $geom,
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    private function layerSimples(string $table, string $nameCol, int $tenantId, ?array $bbox): array
    {
        $q = DB::table($table)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('geo')
            ->selectRaw("id, {$nameCol} as name, ST_AsGeoJSON(geo, 6) as geo_json");

        $this->applyBbox($q, 'geo', $bbox);

        $features = [];
        foreach ($q->get() as $row) {
            $geom = json_decode($row->geo_json);
            if (! $geom || empty($geom->coordinates)) {
                continue;
            }
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'id' => $row->id,
                    'name' => $row->name ?? 'S/N',
                    'layer' => $table,
                ],
                'geometry' => $geom,
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    private function applyBbox($query, string $col, ?array $bbox): void
    {
        if ($bbox && count($bbox) === 4) {
            [$west, $south, $east, $north] = $bbox;
            $query->whereRaw(
                "{$col} && ST_MakeEnvelope(?, ?, ?, ?, 4326)",
                [(float) $west, (float) $south, (float) $east, (float) $north]
            );
        }
    }
}
