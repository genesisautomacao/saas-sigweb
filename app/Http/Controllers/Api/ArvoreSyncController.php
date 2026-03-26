<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Arvore;

class ArvoreSyncController extends Controller
{
    /**
     * Rota PULL: O celular chama essa rota no Wi-Fi para BAIXAR as árvores pro mapa offline.
     */
    public function pull(Request $request)
    {
        $tenantId = $request->user()->tenants()->first()->id;
        
        // Busca as árvores e formata para a linguagem do banco offline do celular (WatermelonDB)
        $arvores = Arvore::where('tenant_id', $tenantId)->get()->map(function($arvore) {
            return [
                'id' => $arvore->code, // O banco do celular EXIGE que o ID principal seja o nosso UUID
                'address' => $arvore->address,
                'botanical_species' => $arvore->botanical_species,
                'botanical_family' => $arvore->botanical_family,
                'size' => $arvore->size,
                'trunk_diameter_dap' => $arvore->trunk_diameter_dap ? (float) $arvore->trunk_diameter_dap : null,
                'canopy_diameter' => $arvore->canopy_diameter ? (float) $arvore->canopy_diameter : null,
                'total_height' => $arvore->total_height ? (float) $arvore->total_height : null,
                'canopy_height' => $arvore->canopy_height ? (float) $arvore->canopy_height : null,
                'phytosanitary_condition' => $arvore->phytosanitary_condition,
                'general_state' => $arvore->general_state,
                'root_system' => $arvore->root_system,
                'urban_interferences' => $arvore->urban_interferences,
                'risk_potential' => $arvore->risk_potential ? (int) $arvore->risk_potential : null,
                'observations' => $arvore->observations,
                // Extrai o Lat/Lon do GeoJSON
                'lat' => $arvore->geo_json ? $arvore->geo_json->coordinates[1] : null,
                'lon' => $arvore->geo_json ? $arvore->geo_json->coordinates[0] : null,
            ];
        });

        // O WatermelonDB espera receber as mudanças (changes) neste formato exato
        return response()->json([
            'changes' => [
                'arvores' => [
                    'created' => $arvores,
                    'updated' => [],
                    'deleted' => [],
                ]
            ],
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Rota PUSH: O celular chama essa rota para ENVIAR o pacote de árvores (criadas/excluídas/editadas) offline.
     */
    public function push(Request $request)
    {
        $tenantId = $request->user()->tenants()->first()->id;
        $changes = $request->input('changes'); // Pega o pacotão ZIP/JSON do celular

        if (!isset($changes['arvores'])) {
            return response()->json(['message' => 'Nada para sincronizar'], 200);
        }

        // 🛑 BLINDAGEM: Database Transaction. Se a internet cair no meio, ele desfaz tudo!
        DB::beginTransaction();

        try {
            // 1. CRIADOS: O fiscal criou árvores novas offline? Vamos salvar!
            if (!empty($changes['arvores']['created'])) {
                foreach ($changes['arvores']['created'] as $arvoreApp) {
                    $arvore = new Arvore();
                    $arvore->tenant_id = $tenantId;
                    $arvore->code = $arvoreApp['id']; // O UUID complexo gerado lá no celular!
                    
                    // Dados florestais da tabela
                    $arvore->address = $arvoreApp['address'] ?? null;
                    $arvore->botanical_species = $arvoreApp['botanical_species'] ?? null;
                    $arvore->botanical_family = $arvoreApp['botanical_family'] ?? null;
                    $arvore->size = $arvoreApp['size'] ?? null;
                    $arvore->trunk_diameter_dap = $arvoreApp['trunk_diameter_dap'] ?? null;
                    $arvore->canopy_diameter = $arvoreApp['canopy_diameter'] ?? null;
                    $arvore->total_height = $arvoreApp['total_height'] ?? null;
                    $arvore->canopy_height = $arvoreApp['canopy_height'] ?? null;
                    $arvore->phytosanitary_condition = $arvoreApp['phytosanitary_condition'] ?? null;
                    $arvore->general_state = $arvoreApp['general_state'] ?? null;
                    $arvore->root_system = $arvoreApp['root_system'] ?? null;
                    $arvore->urban_interferences = $arvoreApp['urban_interferences'] ?? null;
                    $arvore->risk_potential = $arvoreApp['risk_potential'] ?? null;
                    $arvore->observations = $arvoreApp['observations'] ?? null;

                    // 🛑 A MÁGICA GEOGRÁFICA: Transforma a coordenada do celular no PostGIS
                    if (isset($arvoreApp['lat']) && isset($arvoreApp['lon'])) {
                        $arvore->geo = [
                            "type" => "Point",
                            "coordinates" => [$arvoreApp['lon'], $arvoreApp['lat']]
                        ];
                    }

                    $arvore->save();
                }
            }

            // 2. ATUALIZADOS: O fiscal editou os dados ou moveu a árvore de lugar no app?
            if (!empty($changes['arvores']['updated'])) {
                foreach ($changes['arvores']['updated'] as $arvoreApp) {
                    $arvore = Arvore::where('tenant_id', $tenantId)
                                    ->where('code', $arvoreApp['id'])
                                    ->first();

                    if ($arvore) {
                        // Atualiza os dados florestais
                        $arvore->address = $arvoreApp['address'] ?? null;
                        $arvore->botanical_species = $arvoreApp['botanical_species'] ?? null;
                        $arvore->botanical_family = $arvoreApp['botanical_family'] ?? null;
                        $arvore->size = $arvoreApp['size'] ?? null;
                        $arvore->trunk_diameter_dap = $arvoreApp['trunk_diameter_dap'] ?? null;
                        $arvore->canopy_diameter = $arvoreApp['canopy_diameter'] ?? null;
                        $arvore->total_height = $arvoreApp['total_height'] ?? null;
                        $arvore->canopy_height = $arvoreApp['canopy_height'] ?? null;
                        $arvore->phytosanitary_condition = $arvoreApp['phytosanitary_condition'] ?? null;
                        $arvore->general_state = $arvoreApp['general_state'] ?? null;
                        $arvore->root_system = $arvoreApp['root_system'] ?? null;
                        $arvore->urban_interferences = $arvoreApp['urban_interferences'] ?? null;
                        $arvore->risk_potential = $arvoreApp['risk_potential'] ?? null;
                        $arvore->observations = $arvoreApp['observations'] ?? null;

                        // Atualiza a geometria (caso o pino tenha sido arrastado no mapa do app)
                        if (isset($arvoreApp['lat']) && isset($arvoreApp['lon'])) {
                            $arvore->geo = [
                                "type" => "Point",
                                "coordinates" => [$arvoreApp['lon'], $arvoreApp['lat']]
                            ];
                        }

                        $arvore->save();
                    }
                }
            }

            // 3. DELETADOS: O fiscal apagou árvores no celular?
            if (!empty($changes['arvores']['deleted'])) {
                foreach ($changes['arvores']['deleted'] as $arvoreAppId) {
                    // O WatermelonDB envia um array simples de IDs (UUIDs) para exclusão
                    $arvore = Arvore::where('tenant_id', $tenantId)
                                    ->where('code', $arvoreAppId)
                                    ->first();

                    if ($arvore) {
                        // Como o Model tem SoftDeletes, isso apenas preenche a coluna deleted_at
                        $arvore->delete(); 
                    }
                }
            }

            DB::commit();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            // Retorna o erro 500 para o app saber que falhou e tentar de novo mais tarde
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}