<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        // 1. Busca o ID do Tenant real baseado na Slug da URL
        // (Assumindo que sua tabela de tenants se chama 'tenants'. Ajuste se for diferente, ex: 'prefeituras')
        $tenantId = DB::table('tenants')->where('slug', $tenant_slug)->value('id');

        if (!$tenantId) {
            return response()->json(['error' => 'Prefeitura/Tenant não encontrada.'], 404);
        }

        $service = strtoupper($request->query('service', 'WFS'));
        $requestType = strtoupper($request->query('request', ''));

        // Atende às requisições do protocolo WFS (Web Feature Service)
        if ($service === 'WFS') {
            if ($requestType === 'GETCAPABILITIES') {
                return $this->wfsGetCapabilities($tenant_slug);
            }
            if ($requestType === 'GETFEATURE') {
                return $this->wfsGetFeature($request, $tenantId);
            }
        }

        return response()->json(['error' => 'Serviço OGC ou Request não suportado pelo SIGWEB.'], 400);
    }

    /**
     * Responde com o XML descritivo dos serviços disponíveis (Padrão OGC OBRIGATÓRIO)
     */
    private function wfsGetCapabilities($tenant_slug)
    {
        // O XML agora é dinâmico e pode no futuro refletir apenas as camadas que aquela prefeitura tem acesso
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <WFS_Capabilities version="1.0.0" xmlns="http://www.opengis.net/wfs">
            <Service>
                <Name>SIGWEB WFS - ' . strtoupper($tenant_slug) . '</Name>
                <Title>Serviço de Feições OGC SIGWEB</Title>
                <Abstract>Fornecimento interoperável de dados espaciais via WFS (GeoJSON)</Abstract>
            </Service>
            <FeatureTypeList>
                <FeatureType><Name>lotes</Name><Title>Lotes Urbanos</Title></FeatureType>
                <FeatureType><Name>quadras</Name><Title>Quadras Urbanas</Title></FeatureType>
                <FeatureType><Name>logradouros</Name><Title>Sistema Viário</Title></FeatureType>
                <FeatureType><Name>bairros</Name><Title>Bairros e Distritos</Title></FeatureType>
            </FeatureTypeList>
        </WFS_Capabilities>';

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }

    /**
     * Motor principal que busca a geometria no PostGIS e devolve no padrão WFS (GeoJSON)
     */
    private function wfsGetFeature(Request $request, $tenantId)
    {
        // No padrão OGC, a camada é solicitada via parâmetro typeName
        $typeName = strtolower($request->query('typeName', $request->query('typename', '')));

        // 🛑 DICIONÁRIO DE MAPEAMENTO (Tabela Real e Coluna de Rótulo/Nome)
        $allowedLayers = [
            'lotes'       => ['table' => 'lotes',       'label_col' => 'numero_lote'],
            'quadras'     => ['table' => 'quadras',     'label_col' => 'name'],
            'logradouros' => ['table' => 'logradouros', 'label_col' => 'name'],
            'bairros'     => ['table' => 'bairros',     'label_col' => 'name'],
        ];

        if (!array_key_exists($typeName, $allowedLayers)) {
            return response()->json(['error' => "Layer '{$typeName}' não encontrada ou não configurada para exportação OGC WFS."], 404);
        }

        $config = $allowedLayers[$typeName];
        $tableName = $config['table'];
        $labelCol = $config['label_col'];

        // Busca os dados e usa a Super Força do PostGIS (ST_AsGeoJSON) para converter a geometria
        // Forçamos um alias "feature_name" para o rótulo, assim o PHP lê padronizado
        $query = DB::table($tableName)
            ->select('id', "{$labelCol} as feature_name", DB::raw('ST_AsGeoJSON(geo::geometry) as geo_json'))
            ->whereNotNull('geo')
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