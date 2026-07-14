<?php

namespace App\Services\Processo;

use App\Models\ProcessoAnexo;
use App\Models\ProcessoDigital;
use Illuminate\Support\Collection;

/**
 * PD-2 — Checklist de análise por anexo.
 * O analista aprova/reprova cada anexo do solicitante individualmente (com observação
 * nos reprovados) antes de julgar a etapa. Só a ÚLTIMA versão de cada cadeia
 * (anexo_origem_id) é analisável; substituição pelo cidadão nasce 'pendente' de novo.
 */
class ProcessoChecklistService
{
    /**
     * Anexos do processo sujeitos ao checklist: tipos analisáveis, enviados pelo requerente.
     * Retorna TODOS (para a listagem), com o conjunto de IDs de "última versão" resolvível
     * via ultimasVersoes() para saber quais recebem botões.
     */
    public static function anexosAnalisaveis(ProcessoDigital $processo): Collection
    {
        return ProcessoAnexo::withoutGlobalScopes()
            ->where('processo_digital_id', $processo->id)
            ->whereIn('tipo_anexo', ProcessoAnexo::TIPOS_ANALISAVEIS)
            ->where('usuario_id', $processo->requerente_id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();
    }

    /** IDs dos anexos que são a última versão da sua cadeia (os únicos com botões/status vigente). */
    public static function ultimasVersoes(Collection $anexos): Collection
    {
        return $anexos
            ->groupBy(fn (ProcessoAnexo $a) => $a->cadeiaId())
            ->map(fn (Collection $grupo) => $grupo->sortByDesc('id')->first()->id)
            ->values();
    }

    /** O anexo pode ser aprovado/reprovado agora? (etapa do analista + processo ativo + última versão). */
    public static function podeAnalisar(ProcessoAnexo $anexo, ProcessoDigital $processo): bool
    {
        if ((int) $anexo->processo_digital_id !== (int) $processo->id) {
            return false;
        }

        if (! in_array($anexo->tipo_anexo, ProcessoAnexo::TIPOS_ANALISAVEIS)) {
            return false;
        }

        if ($processo->etapaAtual?->executor !== 'analista') {
            return false;
        }

        if (in_array($processo->status, ['concluido', 'cancelado'])) {
            return false;
        }

        $anexos = self::anexosAnalisaveis($processo);

        return self::ultimasVersoes($anexos)->contains($anexo->id);
    }

    /** Marca a decisão do analista sobre um anexo (aprovado/reprovado + observação). */
    public static function marcar(ProcessoAnexo $anexo, string $status, ?string $observacao, int $usuarioId): void
    {
        $anexo->update([
            'status_analise' => $status,
            'observacao_analise' => $status === 'reprovado' ? $observacao : null,
            'analisado_por_id' => $usuarioId,
            'analisado_em' => now(),
        ]);
    }

    /** Últimas versões com status 'reprovado' — bloqueiam a aprovação da etapa. */
    public static function reprovadosPendentes(ProcessoDigital $processo): Collection
    {
        $anexos = self::anexosAnalisaveis($processo);
        $ultimas = self::ultimasVersoes($anexos);

        return $anexos
            ->filter(fn (ProcessoAnexo $a) => $ultimas->contains($a->id) && $a->status_analise === 'reprovado')
            ->values();
    }

    /** Sufixo do parecer com os itens reprovados (flui para tramitação + e-mail de correção). */
    public static function resumoReprovados(ProcessoDigital $processo): ?string
    {
        $reprovados = self::reprovadosPendentes($processo);

        if ($reprovados->isEmpty()) {
            return null;
        }

        $linhas = $reprovados->map(function (ProcessoAnexo $a) {
            $obs = $a->observacao_analise ? (': '.$a->observacao_analise) : '';

            return '— '.$a->nome_arquivo.$obs;
        });

        return "Documentos reprovados:\n".$linhas->implode("\n");
    }

    /** Contadores para o resumo do checklist no modal de julgamento. */
    public static function resumoContadores(ProcessoDigital $processo): array
    {
        $anexos = self::anexosAnalisaveis($processo);
        $ultimas = self::ultimasVersoes($anexos);
        $vigentes = $anexos->filter(fn (ProcessoAnexo $a) => $ultimas->contains($a->id));

        return [
            'total' => $vigentes->count(),
            'aprovados' => $vigentes->where('status_analise', 'aprovado')->count(),
            'reprovados' => $vigentes->where('status_analise', 'reprovado')->count(),
            'pendentes' => $vigentes->where('status_analise', 'pendente')->count(),
            'itens_reprovados' => $vigentes->where('status_analise', 'reprovado')->values(),
        ];
    }

    /**
     * Última versão do anexo de um campo 'arquivo' da etapa (vínculo por campo_slug;
     * fallback legado por prefixo do label no nome_arquivo, filtrado pela etapa).
     */
    public static function ultimoAnexoDoCampo(ProcessoDigital $processo, int $etapaId, string $slug, string $label): ?ProcessoAnexo
    {
        return ProcessoAnexo::withoutGlobalScopes()
            ->where('processo_digital_id', $processo->id)
            ->where('etapa_id', $etapaId)
            ->where('tipo_anexo', 'formulario')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($slug, $label) {
                $q->where('campo_slug', $slug)
                    ->orWhere(fn ($legado) => $legado->whereNull('campo_slug')->where('nome_arquivo', 'like', $label.' — %'));
            })
            ->orderByDesc('id')
            ->first();
    }
}
