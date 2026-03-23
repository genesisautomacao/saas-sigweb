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
                'botanical_species' => $arvore->botanical_species,
                'phytosanitary_condition' => $arvore->phytosanitary_condition,
                // Extrai o Lat/Lon do GeoJSON que o seu Model já converteu
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
     * Rota PUSH: O celular chama essa rota para ENVIAR o pacote de árvores criadas no mato sem internet.
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
            // 1. O fiscal criou árvores novas offline? Vamos salvar!
            if (!empty($changes['arvores']['created'])) {
                foreach ($changes['arvores']['created'] as $arvoreApp) {
                    $arvore = new Arvore();
                    $arvore->tenant_id = $tenantId;
                    $arvore->code = $arvoreApp['id']; // O UUID complexo gerado lá no celular!
                    
                    // Dados básicos do form
                    $arvore->botanical_species = $arvoreApp['botanical_species'] ?? 'Não Informada';
                    $arvore->phytosanitary_condition = $arvoreApp['phytosanitary_condition'] ?? 'Bom';

                    // 🛑 A MÁGICA GEOGRÁFICA: Transforma a coordenada do celular no PostGIS
                    if (isset($arvoreApp['lat']) && isset($arvoreApp['lon'])) {
                        // Passamos o array para o seu Mutator "setGeoAttribute" fazer o ST_GeomFromGeoJSON
                        $arvore->geo = [
                            "type" => "Point",
                            "coordinates" => [$arvoreApp['lon'], $arvoreApp['lat']]
                        ];
                    }

                    $arvore->save();
                }
            }

            // (Para o MVP da reunião, focar no 'created' já é o suficiente para o queixo deles cair. 
            // Depois implementamos o laço do 'updated' e 'deleted' seguindo a mesma lógica).

            DB::commit();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            // Retorna o erro 500 para o app saber que falhou e tentar de novo mais tarde
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}