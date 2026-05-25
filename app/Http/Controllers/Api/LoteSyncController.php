<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LoteSyncController extends Controller
{
    /**
     * PULL: Baixa todos os lotes da tenant para o app (com geometria para exibir no mapa).
     * Usa raw SQL para evitar N+1 com geometrias MultiPolygon pesadas.
     */
    public function pull(Request $request)
    {
        $tenantId = $request->user()->tenants()->first()->id;

        $lotes = DB::table('lotes')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('geo')
            ->selectRaw('
                code,
                numero_lote,
                quadra_id,
                zona_id,
                area_geo,
                main_facade_length,
                foto_frontal,
                observacao,
                sequential_id,
                ST_AsGeoJSON(geo, 6) as geo_json_raw
            ')
            ->get()
            ->map(fn ($l) => [
                'id'                 => $l->code,
                'numero_lote'        => $l->numero_lote,
                'quadra_id'          => $l->quadra_id,
                'zona_id'            => $l->zona_id,
                'area_geo'           => $l->area_geo !== null ? (float) $l->area_geo : null,
                'main_facade_length' => $l->main_facade_length !== null ? (float) $l->main_facade_length : null,
                'foto_frontal'       => $l->foto_frontal,
                'observacao'         => $l->observacao,
                'sequential_id'      => $l->sequential_id,
                'geo_json'           => $l->geo_json_raw,
            ]);

        return response()->json([
            'changes' => [
                'lotes' => [
                    'created' => $lotes,
                    'updated' => [],
                    'deleted' => [],
                ]
            ],
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * PUSH: Recebe atualizações de lotes do app.
     * O fiscal de campo só pode atualizar lotes existentes (observação e foto).
     * Criação e deleção são feitas apenas pelo painel web.
     */
    public function push(Request $request)
    {
        $tenantId = $request->user()->tenants()->first()->id;
        $changes  = $request->input('changes');

        if (empty($changes['lotes']['updated'])) {
            return response()->json(['message' => 'Nada para sincronizar'], 200);
        }

        DB::beginTransaction();

        try {
            foreach ($changes['lotes']['updated'] as $loteApp) {
                $lote = Lote::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('code', $loteApp['id'])
                    ->first();

                if (!$lote) continue;

                if (array_key_exists('observacao', $loteApp)) {
                    $lote->observacao = $loteApp['observacao'];
                }

                if (!empty($loteApp['foto_frontal']) && str_starts_with($loteApp['foto_frontal'], 'data:image')) {
                    $lote->foto_frontal = $this->salvarImagemBase64($loteApp['foto_frontal']);
                }

                $lote->save();
            }

            DB::commit();
            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function salvarImagemBase64(string $base64String): string
    {
        $imageParts   = explode(';base64,', $base64String);
        $imageTypeAux = explode('image/', $imageParts[0]);
        $imageType    = $imageTypeAux[1] ?? 'jpeg';
        $imageBase64  = base64_decode($imageParts[1]);
        $fileName     = Str::uuid() . '.' . $imageType;
        $filePath     = 'lotes_fotos/' . $fileName;

        Storage::disk('public')->put($filePath, $imageBase64);

        return $filePath;
    }
}
