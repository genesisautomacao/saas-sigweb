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

        // R67-4 — região do cadastrador: null = sem restrição (Master/Gerente);
        // [] = sem atribuição vigente → não baixa nada; array = quadras atribuídas.
        $quadrasPermitidas = \App\Services\Coleta\ColetaRegiaoService::quadrasPermitidas($tenantId, $request->user()->id);

        if ($quadrasPermitidas !== null && empty($quadrasPermitidas)) {
            return response()->json([
                'changes' => ['lotes' => ['created' => [], 'updated' => [], 'deleted' => []]],
                'timestamp' => now()->timestamp,
                'aviso' => 'sem_regiao_atribuida',
                'mensagem' => 'Nenhuma região de trabalho atribuída para hoje. Fale com o supervisor.',
            ]);
        }

        // Query 1: lotes com geometria via raw SQL.
        // Refatoração PoC Tangará: observacao/inconformidade/coletado_* saíram do lote
        // e vivem em coleta_imobiliaria (LEFT JOIN da campanha vigente); situacao_quadra
        // e os atributos descritivos vivem em dados_customizados.
        $campanha = \App\Models\ColetaImobiliaria::CAMPANHA_PADRAO;

        $lotesRaw = DB::table('lotes')
            ->leftJoin('coleta_imobiliaria as ci', function ($join) use ($campanha) {
                $join->on('ci.coletavel_id', '=', 'lotes.id')
                    ->where('ci.coletavel_type', '=', 'App\\Models\\Lote')
                    ->where('ci.campanha', '=', $campanha)
                    ->whereNull('ci.deleted_at');
            })
            ->where('lotes.tenant_id', $tenantId)
            ->whereNull('lotes.deleted_at')
            ->whereNotNull('lotes.geo')
            ->when($quadrasPermitidas !== null, fn ($q) => $q->whereIn('lotes.quadra_id', $quadrasPermitidas))
            ->selectRaw('
                lotes.id,
                lotes.code,
                lotes.numero_lote,
                lotes.quadra_id,
                lotes.zona_id,
                lotes.area_geo,
                lotes.main_facade_length,
                lotes.foto_frontal,
                lotes.foto_lateral_esq,
                lotes.foto_lateral_dir,
                lotes.status_cadastro,
                lotes.ocupacao,
                lotes.dados_customizados,
                lotes.sequential_id,
                ci.observacao,
                ci.inconformidade_descricao,
                ci.inconformidade_ponto,
                ci.coletado_por_id,
                ci.coletado_em,
                ST_AsGeoJSON(lotes.geo, 6) as geo_json_raw
            ')
            ->get();

        if ($lotesRaw->isEmpty()) {
            return response()->json([
                'changes' => ['lotes' => ['created' => [], 'updated' => [], 'deleted' => []]],
                'timestamp' => now()->timestamp,
            ]);
        }

        $loteIds = $lotesRaw->pluck('id')->all();

        // Query 2: unidades imobiliárias (incluindo dados_tributarios do sistema tributário)
        $unidadesAgrupadas = UnidadeImobiliaria::withoutGlobalScopes()
            ->whereIn('lote_id', $loteIds)
            ->whereNull('deleted_at')
            ->get(['id', 'code', 'lote_id', 'inscricao_imobiliaria', 'codigo_imovel_tributario',
                'logradouro_nome', 'numero_imovel', 'nome_edificio', 'dados_tributarios', 'dados_customizados'])
            ->groupBy('lote_id');

        // Query 3: edificações — atributos descritivos vivem em dados_customizados
        $edificacoesAgrupadas = Edificacao::withoutGlobalScopes()
            ->whereIn('lote_id', $loteIds)
            ->whereNull('deleted_at')
            ->get(['id', 'code', 'lote_id', 'area_geo', 'dados_customizados'])
            ->groupBy('lote_id');

        $lotes = $lotesRaw->map(fn ($l) => [
            'id' => $l->code,
            'numero_lote' => $l->numero_lote,
            'quadra_id' => $l->quadra_id,
            'zona_id' => $l->zona_id,
            'area_geo' => $l->area_geo !== null ? (float) $l->area_geo : null,
            'main_facade_length' => $l->main_facade_length !== null ? (float) $l->main_facade_length : null,
            'foto_frontal' => $l->foto_frontal,
            'foto_lateral_esq' => $l->foto_lateral_esq,
            'foto_lateral_dir' => $l->foto_lateral_dir,
            'observacao' => $l->observacao,
            'status_cadastro' => $l->status_cadastro ?? 'nao_visitado',
            'ocupacao' => $l->ocupacao,
            'inconformidade_descricao' => $l->inconformidade_descricao,
            // Compat com o app publicado: o ponto GPS da inconformidade viaja no
            // formato antigo (dados_vistoria.inconformidade_ponto), mas vive na coleta.
            'dados_vistoria' => $l->inconformidade_ponto
                ? ['inconformidade_ponto' => json_decode($l->inconformidade_ponto, true)]
                : null,
            // R67-1 — DB::table não aplica cast: decodifica o JSON manualmente.
            // situacao_quadra e demais atributos do município chegam AQUI (slug => valor).
            'dados_customizados' => $l->dados_customizados ? json_decode($l->dados_customizados, true) : null,
            'coletado_por_id' => $l->coletado_por_id,
            'coletado_em' => $l->coletado_em,
            'sequential_id' => $l->sequential_id,
            // geo_json: decodificar string raw do PostGIS para objeto JSON real
            // (mobile espera {type, coordinates}, não string escapada)
            'geo_json' => $l->geo_json_raw ? json_decode($l->geo_json_raw) : null,
            'unidades_imobiliarias' => ($unidadesAgrupadas[$l->id] ?? collect())->map(fn ($u) => [
                'id' => $u->code,
                'inscricao_imobiliaria' => $u->inscricao_imobiliaria,
                'codigo_imovel_tributario' => $u->codigo_imovel_tributario,
                'logradouro_nome' => $u->logradouro_nome,
                'numero_imovel' => $u->numero_imovel,
                'nome_edificio' => $u->nome_edificio,
                'complemento' => null, // TODO: coluna ainda não existe no banco
                'dados_tributarios' => $u->dados_tributarios,
                'dados_customizados' => $u->dados_customizados, // R67-1
            ])->values(),
            // Atributos descritivos (tipo_edificacao, pavimento, estado...) vêm em
            // dados_customizados — o app monta o formulário pelo /api/coleta/config.
            'edificacoes' => ($edificacoesAgrupadas[$l->id] ?? collect())->map(fn ($e) => [
                'id' => $e->code,
                'area_geo' => $e->area_geo !== null ? (float) $e->area_geo : null,
                'dados_customizados' => $e->dados_customizados, // R67-1
            ])->values(),
        ]);

        return response()->json([
            'changes' => [
                'lotes' => [
                    'created' => $lotes,
                    'updated' => [],
                    'deleted' => [],
                ],
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
        $userId = $request->user()->id;
        $changes = $request->input('changes');

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

                if (! $lote) {
                    continue;
                }

                // Status: campo estrutural, com lista fixa. Ocupação: binário do sistema,
                // com blindagem N1 (app antigo pode mandar o rótulo — traduz p/ a chave).
                if (array_key_exists('status_cadastro', $loteApp)) {
                    $lote->status_cadastro = $loteApp['status_cadastro'];
                }
                if (array_key_exists('ocupacao', $loteApp)) {
                    $lote->ocupacao = \App\Services\Coleta\CampoDominioService::normalizarValor(
                        'lote', 'ocupacao', $loteApp['ocupacao'], $tenantId
                    );
                }

                // R67-1 — campos customizados do município (whitelist por slug + cast por tipo).
                // situacao_quadra e demais atributos que eram coluna chegam AQUI.
                // Compat app antigo: situacao_quadra/dados_vistoria soltos entram no JSON.
                $custom = (array) ($loteApp['dados_customizados'] ?? []);
                if (array_key_exists('situacao_quadra', $loteApp) && ! array_key_exists('situacao_quadra', $custom)) {
                    $custom['situacao_quadra'] = $loteApp['situacao_quadra'];
                }
                if (! empty($loteApp['dados_vistoria']) && is_array($loteApp['dados_vistoria'])) {
                    $custom = array_merge($loteApp['dados_vistoria'], $custom);
                }
                if ($custom !== []) {
                    $lote->dados_customizados = array_merge(
                        (array) $lote->dados_customizados,
                        \App\Services\Coleta\CampoCustomizadoService::filtrarPayload('lote', $custom, $tenantId)
                    );
                }

                // Fotos (3 slots) — aceita base64 ou caminho existente
                foreach (['foto_frontal', 'foto_lateral_esq', 'foto_lateral_dir'] as $fotoField) {
                    if (! empty($loteApp[$fotoField]) && str_starts_with($loteApp[$fotoField], 'data:image')) {
                        $lote->$fotoField = $this->salvarImagemBase64($loteApp[$fotoField]);
                    }
                }

                $lote->save();

                // Coleta (observação, inconformidade, quem/quando) vive em coleta_imobiliaria;
                // registrar() também sincroniza o cache lotes.status_cadastro.
                $dadosColeta = [];
                if (array_key_exists('status_cadastro', $loteApp)) {
                    $dadosColeta['status'] = $loteApp['status_cadastro'];
                }
                if (array_key_exists('observacao', $loteApp)) {
                    $dadosColeta['observacao'] = $loteApp['observacao'];
                }
                if (array_key_exists('inconformidade_descricao', $loteApp)) {
                    $dadosColeta['inconformidade_descricao'] = $loteApp['inconformidade_descricao'];
                }
                // Ponto GPS da inconformidade (contrato antigo: dados_vistoria.inconformidade_ponto)
                $ponto = $loteApp['dados_vistoria']['inconformidade_ponto'] ?? null;
                if ($ponto !== null) {
                    $dadosColeta['inconformidade_ponto'] = $ponto;
                }
                if (isset($loteApp['status_cadastro']) && $loteApp['status_cadastro'] !== 'nao_visitado') {
                    $dadosColeta['coletado_por_id'] = $userId;
                    $dadosColeta['coletado_em'] = now();
                }
                if ($dadosColeta !== []) {
                    \App\Models\ColetaImobiliaria::registrar($lote, $dadosColeta, tenantId: $tenantId);
                }

                // Retificações de edificações (opcional)
                if (! empty($loteApp['edificacoes_updates'])) {
                    foreach ($loteApp['edificacoes_updates'] as $edApp) {
                        $edificacao = Edificacao::withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->where('code', $edApp['id'])
                            ->where('lote_id', $lote->id)
                            ->first();

                        if (! $edificacao) {
                            continue;
                        }

                        if (isset($edApp['area_geo'])) {
                            $edificacao->area_geo = (float) $edApp['area_geo'];
                        }

                        // Atributos descritivos = campos customizados (whitelist por slug).
                        // Compat app antigo: tipo/tp_construcao/etc soltos entram no JSON
                        // (tipo vira tipo_edificacao — slug do kit).
                        $customEd = (array) ($edApp['dados_customizados'] ?? []);
                        $compatEd = [
                            'tipo' => 'tipo_edificacao',
                            'tp_construcao' => 'tp_construcao',
                            'caracteristica_construcao' => 'caracteristica_construcao',
                            'estado_conservacao' => 'estado_conservacao',
                            'pavimento' => 'pavimento',
                        ];
                        foreach ($compatEd as $antigo => $slug) {
                            if (isset($edApp[$antigo]) && ! array_key_exists($slug, $customEd)) {
                                $customEd[$slug] = $edApp[$antigo];
                            }
                        }
                        if ($customEd !== []) {
                            $edificacao->dados_customizados = array_merge(
                                (array) $edificacao->dados_customizados,
                                \App\Services\Coleta\CampoCustomizadoService::filtrarPayload('edificacao', $customEd, $tenantId)
                            );
                        }

                        $edificacao->save();
                    }
                }

                // R67-1 — campos customizados das unidades imobiliárias (opcional)
                if (! empty($loteApp['unidades_updates'])) {
                    foreach ($loteApp['unidades_updates'] as $uniApp) {
                        $unidade = UnidadeImobiliaria::withoutGlobalScopes()
                            ->where('tenant_id', $tenantId)
                            ->where('code', $uniApp['id'] ?? null)
                            ->where('lote_id', $lote->id)
                            ->first();

                        if (! $unidade || ! array_key_exists('dados_customizados', $uniApp)) {
                            continue;
                        }

                        $unidade->dados_customizados = \App\Services\Coleta\CampoCustomizadoService::filtrarPayload(
                            'unidade', (array) $uniApp['dados_customizados'], $tenantId
                        );
                        $unidade->save();
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
        $imageParts = explode(';base64,', $base64String);
        $imageTypeAux = explode('image/', $imageParts[0]);
        $imageType = $imageTypeAux[1] ?? 'jpeg';
        $imageBase64 = base64_decode($imageParts[1]);
        $fileName = Str::uuid().'.'.$imageType;
        $filePath = 'lotes_fotos/'.$fileName;

        Storage::disk('public')->put($filePath, $imageBase64);

        return $filePath;
    }
}
