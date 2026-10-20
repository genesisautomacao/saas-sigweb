<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\MapaPermissoes;
use App\Support\Modulos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SIGWEB - OGC Interoperability Gateway
 * Implementação nativa dos padrões Open Geospatial Consortium (WFS/WMS)
 */
class OgcController extends Controller
{
    /**
     * Ponto de entrada único para o Serviço OGC do SIGWEB
     */
    public function handle(Request $request, $tenant_slug)
    {
        // R84-2: rota sem login — mesma régua do mapa público (prefeitura ativa, só
        // camadas públicas de módulo contratado). Antes servia qualquer slug, inclusive
        // de prefeitura desativada e com o módulo desligado.
        $tenant = \App\Models\Tenant::where('slug', $tenant_slug)->where('is_active', true)->first();

        if (! $tenant) {
            return response()->json(['error' => 'Prefeitura/Tenant não encontrada.'], 404);
        }

        $tenantId = $tenant->id;

        $service = strtoupper($request->query('service', 'WFS'));
        $requestType = strtoupper($request->query('request', ''));

        // Atende às requisições do protocolo WFS (Web Feature Service)
        if ($service === 'WFS') {
            if ($requestType === 'GETCAPABILITIES') {
                return $this->wfsGetCapabilities($tenant);
            }
            if ($requestType === 'GETFEATURE') {
                return $this->wfsGetFeature($request, $tenant);
            }
        }

        return response()->json(['error' => 'Serviço OGC ou Request não suportado pelo SIGWEB.'], 400);
    }

    /**
     * Responde com o XML descritivo dos serviços disponíveis (Padrão OGC OBRIGATÓRIO)
     */
    /** Camadas do WFS: tabela, coluna de rótulo e título. */
    private const CAMADAS = [
        'lotes' => ['table' => 'lotes', 'label_col' => 'numero_lote', 'titulo' => 'Lotes Urbanos'],
        'quadras' => ['table' => 'quadras', 'label_col' => 'name', 'titulo' => 'Quadras Urbanas'],
        'logradouros' => ['table' => 'logradouros', 'label_col' => 'name', 'titulo' => 'Sistema Viário'],
        'bairros' => ['table' => 'bairros', 'label_col' => 'name', 'titulo' => 'Bairros e Distritos'],
        'setores_fiscais' => ['table' => 'setores_fiscais', 'label_col' => 'name', 'titulo' => 'Setores Fiscais'], // B9
    ];

    /**
     * R84-2 — a camada pode sair por esta rota anônima? Só se for pública (a do mapa do
     * cidadão, `config/mapa.php`) e de módulo contratado pela prefeitura.
     */
    private function camadaLiberada(string $camada, Tenant $tenant): bool
    {
        return MapaPermissoes::camadaPublica($camada) && Modulos::camadaDisponivel($camada, $tenant);
    }

    private function wfsGetCapabilities(Tenant $tenant)
    {
        $tipos = '';
        foreach (self::CAMADAS as $nome => $cfg) {
            if ($this->camadaLiberada($nome, $tenant)) {
                $tipos .= "<FeatureType><Name>{$nome}</Name><Title>{$cfg['titulo']}</Title></FeatureType>";
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <WFS_Capabilities version="1.0.0" xmlns="http://www.opengis.net/wfs">
            <Service>
                <Name>SIGWEB WFS - ' . e(strtoupper($tenant->slug)) . '</Name>
                <Title>Serviço de Feições OGC SIGWEB</Title>
                <Abstract>Fornecimento interoperável de dados espaciais via WFS (GeoJSON)</Abstract>
            </Service>
            <FeatureTypeList>' . $tipos . '</FeatureTypeList>
        </WFS_Capabilities>';

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Motor principal que busca a geometria no PostGIS e devolve no padrão WFS (GeoJSON)
     */
    private function wfsGetFeature(Request $request, Tenant $tenant)
    {
        $tenantId = $tenant->id;

        // No padrão OGC, a camada é solicitada via parâmetro typeName
        $typeName = strtolower($request->query('typeName', $request->query('typename', '')));

        if (! array_key_exists($typeName, self::CAMADAS) || ! $this->camadaLiberada($typeName, $tenant)) {
            return response()->json(['error' => "Layer '{$typeName}' não encontrada ou não configurada para exportação OGC WFS."], 404);
        }

        $config = self::CAMADAS[$typeName];
        $tableName = $config['table'];
        $labelCol = $config['label_col'];

        // Busca os dados e usa a Super Força do PostGIS (ST_AsGeoJSON) para converter a geometria
        // Forçamos um alias "feature_name" para o rótulo, assim o PHP lê padronizado
        $query = DB::table($tableName)
            ->select('id', "{$labelCol} as feature_name", DB::raw('ST_AsGeoJSON(geo::geometry) as geo_json'))
            ->whereNotNull('geo')
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenantId);

        // Suporte ao filtro espacial BBOX (Bounding Box) do padrão OGC para exportações parciais (QGIS usa muito isso)
        $bbox = $request->query('bbox');
        if ($bbox) {
            $coords = explode(',', $bbox);
            if (count($coords) >= 4) {
                $minX = (float)$coords[0];
                $minY = (float)$coords[1];
                $maxX = (float)$coords[2];
                $maxY = (float)$coords[3];
                // Usa o motor espacial nativo do banco para filtrar
                $query->whereRaw("ST_Intersects(geo::geometry, ST_MakeEnvelope(?, ?, ?, ?, 4326))", [$minX, $minY, $maxX, $maxY]);
            }
        }

        // Limitamos para evitar estouro de memória em municípios gigantes. (Softwares GIS fazem paginação via BBOX)
        $results = $query->limit(2000)->get();

        $features = [];
        foreach ($results as $item) {
            if ($item->geo_json) {
                // Monta a estrutura rigorosa GeoJSON exigida pela especificação WFS
                $features[] = [
                    'type' => 'Feature',
                    'properties' => [
                        'id' => $item->id,
                        'name' => $item->feature_name ?? 'S/N', // Agora pega a variável padronizada
                    ],
                    'geometry' => json_decode($item->geo_json)
                ];
            }
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'name' => $typeName,
            'crs' => [
                'type' => 'name', 
                'properties' => ['name' => 'urn:ogc:def:crs:EPSG::4326']
            ],
            'features' => $features
        ], 200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*' // Essencial para WFS ser lido por outros softwares externos
        ]);
    }
}