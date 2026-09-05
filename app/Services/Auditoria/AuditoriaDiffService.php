<?php

namespace App\Services\Auditoria;

use App\Models\CampoCustomizado;
use Illuminate\Support\Str;

/**
 * Diff legível de uma atividade da Auditoria — FONTE ÚNICA do modal "Ver detalhes"
 * (auditoria-detalhes.blade.php) e dos exports PDF/Excel (AuditoriaExportService).
 *
 * Coluna JSON (dados_customizados, dados_tributarios, dados_vistoria, opcoes…) é
 * EXPLODIDA chave a chave e, numa atualização, só as chaves que MUDARAM entram —
 * o Spatie grava o objeto inteiro antes/depois, e despejar tudo escondia a alteração
 * (pedido 2026-09-05: edificação com 5+ campos do município virava uma parede de JSON).
 * Chave de campo customizado ganha o rótulo do município (inclusive de campo já
 * desativado/excluído — a trilha é histórica).
 */
final class AuditoriaDiffService
{
    /** class_basename do model → entidade do CampoCustomizado (só onde difere do snake_case). */
    private const ENTIDADE_POR_MODEL = [
        'UnidadeImobiliaria' => 'unidade',
        'PerimetroUrbano' => 'perimetro',
    ];

    /** Colunas JSON cujas chaves são campos customizados do município. */
    private const COLUNAS_CUSTOM = ['dados_customizados'];

    /** @var array<string, array<string, string>> memo tenant|entidade → [slug => label] */
    private static array $rotulos = [];

    /**
     * Linhas do diff, na ordem em que o log gravou as colunas.
     *
     * @return array<int, array{coluna: string, chave: ?string, rotulo: string, antes: mixed, depois: mixed, mudou: bool}>
     */
    public static function linhas($atividade): array
    {
        $props = collect($atividade->properties ?? []);
        $novos = (array) $props->get('attributes', []);
        $antigos = (array) $props->get('old', []);
        $evento = $atividade->event ?: $atividade->description;
        $entidade = self::entidade($atividade);
        $tenantId = self::tenantId($atividade);

        $linhas = [];
        foreach (array_unique(array_merge(array_keys($novos), array_keys($antigos))) as $coluna) {
            if (in_array($coluna, ['geo', 'geo_json'], true)) {
                continue; // geometria = croqui, nunca tabela
            }

            $temNovo = array_key_exists($coluna, $novos);
            $temAntigo = array_key_exists($coluna, $antigos);
            $novo = $temNovo ? $novos[$coluna] : null;
            $antigo = $temAntigo ? $antigos[$coluna] : null;

            if (self::ehObjeto($novo) || self::ehObjeto($antigo)) {
                $novoArr = (array) ($novo ?? []);
                $antigoArr = (array) ($antigo ?? []);
                $rotulos = in_array($coluna, self::COLUNAS_CUSTOM, true) ? self::rotulosCustom($entidade, $tenantId) : [];
                $antesDaColuna = count($linhas);

                foreach (array_unique(array_merge(array_keys($novoArr), array_keys($antigoArr))) as $chave) {
                    $vNovo = $novoArr[$chave] ?? null;
                    $vAntigo = $antigoArr[$chave] ?? null;
                    $mudou = ! $temAntigo || ! $temNovo || self::normaliza($vNovo) !== self::normaliza($vAntigo);
                    if (! $mudou && $evento === 'updated') {
                        continue; // atualização: só o que mudou
                    }
                    $linhas[] = [
                        'coluna' => $coluna,
                        'chave' => (string) $chave,
                        'rotulo' => $rotulos[$chave] ?? self::humaniza((string) $chave),
                        'antes' => $temAntigo ? $vAntigo : null,
                        'depois' => $temNovo ? $vNovo : null,
                        'mudou' => $mudou,
                    ];
                }

                if (count($linhas) === $antesDaColuna) {
                    // JSON regravado sem diferença de conteúdo (ex.: ordem das chaves) — não some da trilha
                    $linhas[] = ['coluna' => $coluna, 'chave' => null, 'rotulo' => $coluna, 'antes' => '(sem diferença de conteúdo)', 'depois' => '(sem diferença de conteúdo)', 'mudou' => false];
                }

                continue;
            }

            $linhas[] = [
                'coluna' => $coluna,
                'chave' => null,
                'rotulo' => $coluna,
                'antes' => $antigo,
                'depois' => $novo,
                'mudou' => ! $temAntigo || ! $temNovo || self::normaliza($novo) !== self::normaliza($antigo),
            ];
        }

        return $linhas;
    }

    /**
     * Texto único p/ exports: "rótulo: antes → depois · rótulo2: valor". Geometria vira a
     * nota do croqui; valores longos são truncados (papel não é banco).
     */
    public static function resumo($atividade, int $limite = 600): string
    {
        $props = collect($atividade->properties ?? []);
        $novos = (array) $props->get('attributes', []);
        $antigos = (array) $props->get('old', []);
        if ($novos === [] && $antigos === []) {
            return '—';
        }

        $curto = fn ($v): string => mb_strlen($t = self::formatar($v, 'vazio')) > 60 ? mb_substr($t, 0, 57).'…' : $t;
        $partes = [];
        if (isset($novos['geo']) || isset($novos['geo_json']) || isset($antigos['geo']) || isset($antigos['geo_json'])) {
            $partes[] = 'geometria: alterada (croqui em Auditoria → Ver detalhes)';
        }
        foreach (self::linhas($atividade) as $l) {
            $partes[] = ($l['mudou'] && $l['antes'] !== null && $l['depois'] !== null) || ($l['mudou'] && $atividade->event === 'updated')
                ? "{$l['rotulo']}: {$curto($l['antes'])} → {$curto($l['depois'])}"
                : "{$l['rotulo']}: {$curto($l['depois'] ?? $l['antes'])}";
        }

        $texto = implode(' · ', $partes);

        return mb_strlen($texto) > $limite ? mb_substr($texto, 0, $limite - 3).'…' : $texto;
    }

    /** Valor legível p/ uma célula (array de escolha múltipla vira lista; objeto vira JSON compacto). */
    public static function formatar(mixed $v, string $vazio = '—'): string
    {
        if ($v === null || $v === '') {
            return $vazio;
        }
        if (is_bool($v)) {
            return $v ? 'Sim' : 'Não';
        }
        if (is_array($v)) {
            $escalares = array_filter($v, fn ($x) => is_scalar($x) || $x === null);

            return count($escalares) === count($v) && ! self::ehObjeto($v)
                ? implode(', ', array_map(fn ($x) => (string) ($x ?? ''), $escalares))
                : (string) json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        if (is_object($v)) {
            return (string) json_encode($v, JSON_UNESCAPED_UNICODE);
        }

        return (string) $v;
    }

    /** Rótulo bonito p/ a coluna (ex.: dados_customizados → "Campos do município"). */
    public static function rotuloColuna(string $coluna): string
    {
        return match ($coluna) {
            'dados_customizados' => 'Campos do município',
            'dados_tributarios' => 'Dados tributários',
            'dados_vistoria' => 'Boletim de vistoria',
            default => self::humaniza($coluna),
        };
    }

    // ── internos ────────────────────────────────────────────────────────

    /** Array associativo (objeto JSON) — lista simples e vazio NÃO contam. */
    private static function ehObjeto(mixed $v): bool
    {
        if (is_object($v)) {
            return true;
        }

        return is_array($v) && $v !== [] && array_keys($v) !== range(0, count($v) - 1);
    }

    private static function normaliza(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }

        return is_scalar($v) ? (string) $v : (string) json_encode($v, JSON_UNESCAPED_UNICODE);
    }

    private static function humaniza(string $chave): string
    {
        return Str::ucfirst(str_replace('_', ' ', $chave));
    }

    private static function entidade($atividade): ?string
    {
        if (! $atividade->subject_type) {
            return null;
        }
        $base = class_basename($atividade->subject_type);
        $entidade = self::ENTIDADE_POR_MODEL[$base] ?? Str::snake($base);

        return array_key_exists($entidade, CampoCustomizado::ENTIDADES) ? $entidade : null;
    }

    private static function tenantId($atividade): ?int
    {
        try {
            $id = $atividade->subject?->tenant_id;
        } catch (\Throwable) {
            $id = null;
        }
        if (! $id && function_exists('filament')) {
            $id = filament()->getTenant()?->id;
        }

        return $id ? (int) $id : null;
    }

    /** slug => label dos campos customizados da entidade, incluindo desativados/excluídos (trilha histórica). */
    private static function rotulosCustom(?string $entidade, ?int $tenantId): array
    {
        if (! $entidade || ! $tenantId) {
            return [];
        }

        return self::$rotulos["{$tenantId}|{$entidade}"] ??= CampoCustomizado::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('entidade', $entidade)
            ->pluck('label', 'slug')
            ->all();
    }
}
