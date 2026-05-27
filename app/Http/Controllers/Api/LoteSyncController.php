<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Edificacao;
use App\Models\Lote;
use App\Models\UnidadeImobiliaria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LoteSyncController extends Controller
{
    /**
     * PULL: Baixa lotes com geometria + unidades imobiliárias + edificações.
     * Usa 3 queries flat (sem N+1) para manter performance com geometrias pesadas.
     */
    public function pull(Request $request)
    {
        $tenantId = $request->user()->tenants()->first()->id;

        // Query 1: lotes com geometria via raw SQL
        $lotesRaw = DB::table('lotes')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('geo')
            ->selectRaw('
                id,
                code,
                numero_lote,
                quadra_id,
                zona_id,
                area_geo,
                main_facade_length,
                foto_frontal,
                foto_lateral_esq,
                foto_lateral_dir,
                observacao,
                status_cadastro,
                ocupacao,
                situacao_quadra,
                inconformidade_descricao,
                dados_vistoria,
                coletado_por_id,
                coletado_em,
                sequential_id,
                ST_AsGeoJSON(geo, 6) as geo_json_raw
            ')
            ->get();

        if ($lotesRaw->isEmpty()) {
            return response()->json([
                'changes'   => ['lotes' => ['created' => [], 'updated' => [], 'deleted' => []]],
                'timestamp' => now()->timestamp,
            ]);
        }

        $loteIds = $lotesRaw->pluck('id')->all();

        // Query 2: unidades imobiliárias (incluindo dados_tributarios do sistema tributário)
        $unidadesAgrupadas = UnidadeImobiliaria::withoutGlobalScopes()
            ->whereIn('lote_id', $loteIds)
            ->whereNull('deleted_at')
            ->get(['id', 'code', 'lote_id', 'inscricao_imobiliaria', 'codigo_imovel_tributario',
                   'logradouro_nome', 'numero_imovel', 'dados_tributarios'])
            ->groupBy('lote_id');

        // Query 3: edificações
        $edificacoesAgrupadas = Edificacao::withoutGlobalScopes()
            ->whereIn('lote_id', $loteIds)
            ->whereNull('deleted_at')
            ->get(['id', 'code', 'lote_id', 'tipo', 'tp_construcao',
                   'caracteristica_construcao', 'estado_conservacao', 'pavimento', 'area_geo'])
            ->groupBy('lote_id');

        $lotes = $lotesRaw->map(fn ($l) => [
            'id'                      => $l->code,
            'numero_lote'             => $l->numero_lote,
            'quadra_id'               => $l->quadra_id,
            'zona_id'                 => $l->zona_id,
            'area_geo'                => $l->area_geo !== null ? (float) $l->area_geo : null,
            'main_facade_length'      => $l->main_facade_length !== null ? (float) $l->main_facade_length : null,
            'foto_frontal'            => $l->foto_frontal,
            'foto_lateral_esq'        => $l->foto_lateral_esq,
            'foto_lateral_dir'        => $l->foto_lateral_dir,
            'observacao'              => $l->observacao,
            'status_cadastro'         => $l->status_cadastro ?? 'nao_visitado',
            'ocupacao'                => $l->ocupacao,
            'situacao_quadra'         => $l->situacao_quadra,
            'inconformidade_descricao'=> $l->inconformidade_descricao,
            'dados_vistoria'          => $l->dados_vistoria ? json_decode($l->dados_vistoria, true) : null,
            'coletado_por_id'         => $l->coletado_por_id,
            'coletado_em'             => $l->coletado_em,
            'sequential_id'           => $l->sequential_id,
            // geo_json: decodificar string raw do PostGIS para objeto JSON real
            // (mobile espera {type, coordinates}, não string escapada)
            'geo_json'                => $l->geo_json_raw ? json_decode($l->geo_json_raw) : null,
            'unidades_imobiliarias'   => ($unidadesAgrupadas[$l->id] ?? collect())->map(fn ($u) => [
                'id'                        => $u->code,
                'inscricao_imobiliaria'     => $u->inscricao_imobiliaria,
                'codigo_imovel_tributario'  => $u->codigo_imovel_tributario,
                'logradouro_nome'           => $u->logradouro_nome,
                'numero_imovel'             => $u->numero_imovel,
                'complemento'               => null, // TODO: coluna ainda não existe no banco
                'dados_tributarios'         => $u->dados_tributarios,
            ])->values(),
            'edificacoes' => ($edificacoesAgrupadas[$l->id] ?? collect())->map(fn ($e) => [
                'id'                       => $e->code,
                'tipo'                     => $e->tipo,
                'tp_construcao'            => $e->tp_construcao,
                'caracteristica_construcao'=> $e->caracteristica_construcao,
                'estado_conservacao'       => $e->estado_conservacao,
                'pavimento'                => $e->pavimento !== null ? (int) $e->pavimento : null,
                'area_geo'                 => $e->area_geo !== null ? (float) $e->area_geo : null,
            ])->values(),
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
     * PUSH: Recebe atualizações do app.
     * O fiscal pode atualizar: status, ocupacao, fotos (3), inconformidade, dados_vistoria.
     * Pode também retificar edificações existentes.
     * Criação e deleção de lotes são feitas apenas pelo painel web.
     */
    public function push(Request $request)
    {
        $tenantId = $request->user()->tenants()->first()->id;
        $userId   = $request->user()->id;
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

                // Campos textuais
                foreach (['observacao', 'status_cadastro', 'ocupacao', 'situacao_quadra', 'inconformidade_descricao'] as $campo) {
                    if (array_key_exists($campo, $loteApp)) {
                        $lote->$campo = $loteApp[$campo];
                    }
                }

                // Boletim flexível (JSON)
                if (array_key_exists('dados_vistoria', $loteApp)) {
                    $lote->dados_vistoria = $loteApp['dados_vistoria'];
                }

                // Fotos (3 slots) — aceita base64 ou caminho existente
                foreach (['foto_frontal', 'foto_lateral_esq', 'foto_lateral_dir'] as $fotoField) {
                    if (!empty($loteApp[$fotoField]) && str_starts_with($loteApp[$fotoField], 'data:image')) {
                        $lote->$fotoField = $this->salvarImagemBase64($loteApp[$fotoField]);
                    }
                }

                // Marcar coletor e data/hora da coleta quando status muda para coletado
                if (isset($loteApp['status_cadastro']) && $loteApp['status_cadastro'] !== 'nao_visitado') {
                    $lote->coletado_por_id = $userId;
                    $lote->coletado_em     = now();
                }

                $lote->save();

                // Retificações de edificações (opcional)
                if (!empty($loteApp['edificacoes_updates'])) {
                    foreach ($loteApp['edificacoes_updates'] as $edApp) {
                        Edificacao::withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->where('code', $edApp['id'])
                            ->where('lote_id', $lote->id)
                            ->update(array_filter([
                                'tipo'                     => $edApp['tipo'] ?? null,
                                'tp_construcao'            => $edApp['tp_construcao'] ?? null,
                                'caracteristica_construcao'=> $edApp['caracteristica_construcao'] ?? null,
                                'estado_conservacao'       => $edApp['estado_conservacao'] ?? null,
                                'pavimento'                => isset($edApp['pavimento']) ? (int) $edApp['pavimento'] : null,
                                'area_geo'                 => isset($edApp['area_geo']) ? (float) $edApp['area_geo'] : null,
                            ], fn ($v) => $v !== null));
                    }
                }
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
