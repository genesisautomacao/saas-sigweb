<?php

namespace App\Services\Coleta;

use App\Models\ColetaImobiliaria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Onda 8/D2 — Relatório de Validação da Coleta (passo 8 do fluxo de campo).
 *
 * Monta, num formato único consumido pela tela, pelo PDF e pelo Excel:
 *  - uma linha por coleta de LOTE da campanha (status, quem/quando, observação,
 *    inconformidade com ponto GPS) com as ALTERAÇÕES antes→depois já rotuladas
 *    (labels do município via CampoCustomizado/CampoDominio);
 *  - a seção de DIVERGÊNCIAS DE PROPRIETÁRIO: campo `proprietario_divergente`
 *    (kit, decisão do usuário) lado a lado com o cadastro oficial (pessoas →
 *    fallback JSON tributário).
 */
class ColetaValidacaoService
{
    /**
     * @param  array{inicio?: string, fim?: string, coletor_id?: int|null, status?: string|null, campanha?: string|null}  $filtros
     * @return array{linhas: array, resumo: array, divergencias: array}
     */
    public static function dados(int $tenantId, array $filtros = []): array
    {
        $campanha = $filtros['campanha'] ?? ColetaImobiliaria::CAMPANHA_PADRAO;

        $q = DB::table('coleta_imobiliaria as ci')
            ->join('lotes', 'lotes.id', '=', 'ci.coletavel_id')
            ->leftJoin('quadras as q', 'q.id', '=', 'lotes.quadra_id')
            ->leftJoin('users as u', 'u.id', '=', 'ci.coletado_por_id')
            ->where('ci.tenant_id', $tenantId)
            ->where('ci.coletavel_type', 'App\\Models\\Lote')
            ->where('ci.campanha', $campanha)
            ->whereNull('ci.deleted_at')
            ->whereNull('lotes.deleted_at')
            ->when(! empty($filtros['coletor_id']), fn ($x) => $x->where('ci.coletado_por_id', $filtros['coletor_id']))
            ->when(! empty($filtros['status']), fn ($x) => $x->where('ci.status', $filtros['status']))
            ->when(! empty($filtros['inicio']), fn ($x) => $x->whereRaw('ci.coletado_em::date >= ?', [$filtros['inicio']]))
            ->when(! empty($filtros['fim']), fn ($x) => $x->whereRaw('ci.coletado_em::date <= ?', [$filtros['fim']]))
            ->orderBy('q.name')
            ->orderBy('lotes.numero_lote')
            ->select([
                'ci.status', 'ci.observacao', 'ci.inconformidade_descricao', 'ci.inconformidade_ponto',
                'ci.alteracoes', 'ci.coletado_em',
                'u.name as coletor',
                'lotes.id as lote_id', 'lotes.numero_lote', 'lotes.sequential_id',
                'q.name as quadra_nome',
            ])
            ->get();

        $rotulos = self::mapaDeRotulos($tenantId);

        $linhas = $q->map(function ($c) use ($rotulos, $tenantId) {
            $ponto = $c->inconformidade_ponto ? json_decode($c->inconformidade_ponto, true) : null;

            return [
                'lote_id' => $c->lote_id,
                'lote' => $c->numero_lote ?: ('#'.$c->sequential_id),
                'quadra' => $c->quadra_nome ?? '—',
                'coletor' => $c->coletor ?? '—',
                'coletado_em' => $c->coletado_em ? \Carbon\Carbon::parse($c->coletado_em)->format('d/m/Y H:i') : '—',
                'status' => $c->status,
                'status_rotulo' => ColetaImobiliaria::STATUS[$c->status] ?? $c->status,
                'observacao' => $c->observacao,
                'inconformidade' => $c->inconformidade_descricao,
                'inconformidade_gps' => $ponto && isset($ponto['lat'], $ponto['lon'])
                    ? number_format((float) $ponto['lat'], 6).', '.number_format((float) $ponto['lon'], 6)
                    : null,
                'alteracoes' => self::rotularAlteracoes(
                    $c->alteracoes ? (array) json_decode($c->alteracoes, true) : [],
                    $rotulos,
                    $tenantId
                ),
            ];
        })->values()->all();

        $resumo = [
            'total' => count($linhas),
            'coletados' => count(array_filter($linhas, fn ($l) => $l['status'] === 'coletado')),
            'pendentes' => count(array_filter($linhas, fn ($l) => $l['status'] === 'pendente')),
            'inconformidades' => count(array_filter($linhas, fn ($l) => $l['status'] === 'inconformidade')),
            'com_alteracoes' => count(array_filter($linhas, fn ($l) => $l['alteracoes'] !== [])),
        ];

        $divergencias = self::divergenciasProprietario($tenantId, array_column($linhas, 'lote_id'));
        $resumo['divergencias'] = count($divergencias);

        return ['linhas' => $linhas, 'resumo' => $resumo, 'divergencias' => $divergencias];
    }

    /** Campanhas existentes na base do tenant (para o filtro). */
    public static function campanhas(int $tenantId): array
    {
        $lista = DB::table('coleta_imobiliaria')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->distinct()
            ->orderBy('campanha')
            ->pluck('campanha')
            ->all();

        return $lista ?: [ColetaImobiliaria::CAMPANHA_PADRAO];
    }

    /** Coletores que registraram coleta (para o filtro). */
    public static function coletores(int $tenantId): array
    {
        return DB::table('coleta_imobiliaria as ci')
            ->join('users as u', 'u.id', '=', 'ci.coletado_por_id')
            ->where('ci.tenant_id', $tenantId)
            ->whereNull('ci.deleted_at')
            ->distinct()
            ->orderBy('u.name')
            ->pluck('u.name', 'u.id')
            ->all();
    }

    // ------------------------------------------------------------------
    // Divergências de proprietário (Frente A4)
    // ------------------------------------------------------------------

    /**
     * Unidades dos lotes filtrados com `proprietario_divergente` preenchido,
     * lado a lado com o proprietário oficial.
     */
    protected static function divergenciasProprietario(int $tenantId, array $loteIds): array
    {
        if ($loteIds === []) {
            return [];
        }

        $unidades = DB::table('unidade_imobiliarias as ui')
            ->join('lotes', 'lotes.id', '=', 'ui.lote_id')
            ->leftJoin('quadras as q', 'q.id', '=', 'lotes.quadra_id')
            ->where('ui.tenant_id', $tenantId)
            ->whereNull('ui.deleted_at')
            ->whereIn('ui.lote_id', $loteIds)
            ->whereRaw("coalesce(ui.dados_customizados->>'proprietario_divergente', '') <> ''")
            ->select([
                'ui.id', 'ui.lote_id', 'ui.inscricao_imobiliaria', 'ui.sequential_id',
                'ui.proprietario_id', 'ui.dados_tributarios', 'ui.dados_customizados',
                'lotes.numero_lote', 'q.name as quadra_nome',
            ])
            ->get();

        if ($unidades->isEmpty()) {
            return [];
        }

        $pessoas = DB::table('pessoas')
            ->whereIn('id', $unidades->pluck('proprietario_id')->filter()->unique())
            ->get(['id', 'name', 'cpf', 'cnpj'])
            ->keyBy('id');

        return $unidades->map(function ($u) use ($pessoas) {
            $custom = (array) json_decode($u->dados_customizados ?? '[]', true);
            $dt = (array) json_decode($u->dados_tributarios ?? '[]', true);
            $pessoa = $u->proprietario_id ? ($pessoas[$u->proprietario_id] ?? null) : null;

            return [
                'lote' => $u->numero_lote ?: ('#'.$u->sequential_id),
                'quadra' => $u->quadra_nome ?? '—',
                'inscricao' => $u->inscricao_imobiliaria ?: ('Unidade #'.$u->sequential_id),
                'oficial_nome' => $pessoa->name ?? ($dt['proprietario_name'] ?? $dt['proprietario_nome'] ?? '—'),
                'oficial_cpf_cnpj' => $pessoa->cpf ?? $pessoa->cnpj ?? ($dt['proprietario_cpf_cnpj'] ?? $dt['cpf'] ?? '—'),
                'divergente_nome' => $custom['proprietario_divergente'] ?? '—',
                'divergente_cpf_cnpj' => $custom['cpf_cnpj_divergente'] ?? '—',
            ];
        })->values()->all();
    }

    // ------------------------------------------------------------------
    // Rótulos das alterações (antes→depois legível)
    // ------------------------------------------------------------------

    /** slug → label dos campos customizados das 3 entidades coletáveis + fixos. */
    protected static function mapaDeRotulos(int $tenantId): array
    {
        $mapa = [
            'lote' => [
                'ocupacao' => CampoDominioService::label('lote', 'ocupacao', $tenantId),
                'foto_frontal' => 'Foto frontal',
                'foto_lateral_esq' => 'Foto lateral esquerda',
                'foto_lateral_dir' => 'Foto lateral direita',
            ],
            'edificacao' => ['area_geo' => 'Área construída (m²)'],
            'unidade' => [],
        ];

        foreach (['lote', 'edificacao', 'unidade'] as $entidade) {
            foreach (CampoCustomizadoService::definicoes($entidade, $tenantId) as $campo) {
                $mapa[$entidade][$campo->slug] = $campo->label;
            }
        }

        return $mapa;
    }

    /**
     * Converte {"lote.custom.slug": {de, para}, "edificacao.12.area_geo": ...}
     * em linhas legíveis. Regra de parse: 1º segmento = entidade, ÚLTIMO = campo,
     * o miolo é a referência (suporta inscrição com pontos: "unidade.01.02...9.slug").
     */
    protected static function rotularAlteracoes(array $alteracoes, array $rotulos, int $tenantId): array
    {
        $linhas = [];

        foreach ($alteracoes as $chave => $diff) {
            $partes = explode('.', (string) $chave);
            $entidade = array_shift($partes);
            $campo = array_pop($partes) ?? '';
            $ref = implode('.', array_filter($partes, fn ($p) => $p !== 'custom'));

            $contexto = match ($entidade) {
                'lote' => 'Lote',
                'edificacao' => trim('Edificação '.$ref),
                'unidade' => trim('Unidade '.$ref),
                default => ucfirst($entidade),
            };

            $rotuloCampo = $rotulos[$entidade][$campo] ?? $campo;

            // Valor legível: ocupação traduz chave→rótulo do município; foto vira marcador
            $legivel = function ($valor) use ($entidade, $campo, $tenantId) {
                if ($valor === null || $valor === '') {
                    return '—';
                }
                if (is_array($valor)) {
                    return implode(', ', $valor);
                }
                if ($entidade === 'lote' && $campo === 'ocupacao') {
                    return CampoDominioService::rotuloValor('lote', 'ocupacao', $valor, $tenantId);
                }
                if (str_starts_with($campo, 'foto_')) {
                    return 'foto enviada';
                }

                return (string) $valor;
            };

            $linhas[] = [
                'contexto' => $contexto,
                'campo' => $rotuloCampo,
                'de' => $legivel($diff['de'] ?? null),
                'para' => $legivel($diff['para'] ?? null),
            ];
        }

        return $linhas;
    }
}
