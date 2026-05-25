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
            'bbox'  => 'nullable|string',
        ]);

        $tenantId = $request->user()->tenants()->first()->id;
        $layer    = $request->query('layer');
        $bbox     = $this->parseBbox($request->query('bbox'));

        $result = $this->buildLayerQuery($layer, $tenantId, $bbox);

        if ($result === null) {
            return response()->json(['error' => 'Camada não encontrada'], 404);
        }

        return response()->json($result);
    }

    private function parseBbox(?string $raw): ?array
    {
        if (!$raw) return null;
        $parts = array_map('floatval', explode(',', $raw));
        return count($parts) === 4 ? $parts : null;
    }

    private function buildLayerQuery(string $layer, int $tenantId, ?array $bbox): ?array
    {
        switch ($layer) {
            case 'lotes':
                return $this->layerLotes($tenantId, $bbox);

            case 'arvores':
                return $this->layerArvores($tenantId, $bbox);

            case 'postes':
                return $this->layerPostes($tenantId, $bbox);

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
            if (!$geom || empty($geom->coordinates)) continue;
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'id'              => $row->id,
                    'name'            => $row->numero_lote ?? 'S/N',
                    'codigo'          => $row->code,
                    'sequential_id'   => $row->sequential_id,
                    'status_cadastro' => $row->status_cadastro ?? 'nao_visitado',
                    'ocupacao'        => $row->ocupacao,
                    'layer'           => 'lotes',
                ],
                'geometry' => $geom,
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    private function layerArvores(int $tenantId, ?array $bbox): array
    {
        $q = DB::table('arvores')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('geo')
            ->selectRaw('id, code, sequential_id, botanical_species, phytosanitary_condition, size, ST_AsGeoJSON(geo, 6) as geo_json');

        $this->applyBbox($q, 'geo', $bbox);

        $features = [];
        foreach ($q->get() as $row) {
            $geom = json_decode($row->geo_json);
            if (!$geom || empty($geom->coordinates)) continue;
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'id'                      => $row->id,
                    'name'                    => $row->sequential_id ? "Árv. #{$row->sequential_id}" : 'S/N',
                    'codigo'                  => $row->code,
                    'sequential_id'           => $row->sequential_id,
                    'botanical_species'       => $row->botanical_species,
                    'phytosanitary_condition' => $row->phytosanitary_condition,
                    'size'                    => $row->size,
                    'layer'                   => 'arvores',
                ],
                'geometry' => $geom,
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    private function layerPostes(int $tenantId, ?array $bbox): array
    {
        $q = DB::table('postes')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('geo')
            ->selectRaw('id, code, sequential_id, structural_condition, ST_AsGeoJSON(geo, 6) as geo_json');

        $this->applyBbox($q, 'geo', $bbox);

        $features = [];
        foreach ($q->get() as $row) {
            $geom = json_decode($row->geo_json);
            if (!$geom || empty($geom->coordinates)) continue;
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'id'                   => $row->id,
                    'name'                 => $row->sequential_id ? "Poste #{$row->sequential_id}" : 'S/N',
                    'codigo'               => $row->code,
                    'sequential_id'        => $row->sequential_id,
                    'structural_condition' => $row->structural_condition,
                    'layer'                => 'postes',
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
            if (!$geom || empty($geom->coordinates)) continue;
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'id'    => $row->id,
                    'name'  => $row->name ?? 'S/N',
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
