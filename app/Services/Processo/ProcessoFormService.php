<?php

namespace App\Services\Processo;

use App\Models\BpmnEtapa;
use App\Models\Lote;
use App\Models\ProcessoAnexo;
use App\Models\ProcessoDigital;
use App\Models\ProcessoResposta;
use Filament\Forms;
use Filament\Support\RawJs;
use Illuminate\Support\Str;

/**
 * Motor de Processos — fluxo híbrido (processosConceito.md §9.2).
 *
 * Centraliza a renderização do formulário DE UMA ETAPA (usado pelo cidadão e pelo analista)
 * e a persistência das respostas por etapa em `processo_respostas` (sem sobrescrever histórico).
 *
 * As respostas ficam namespaced por etapa dentro de `dados_formulario`:
 *   dados_formulario["etapa_<id>"]["<slug-do-campo>"] = valor
 */
class ProcessoFormService
{
    /** Prefixo do state path das respostas de uma etapa dentro de `dados_formulario`. */
    public static function chaveEtapa(int $etapaId): string
    {
        return 'etapa_' . $etapaId;
    }

    /**
     * Monta os componentes Filament do formulário de uma etapa.
     *
     * @param  bool  $disabled  Trava os campos (item 129/220 — B16) ou modo leitura.
     * @return array<\Filament\Forms\Components\Component>
     */
    public static function camposDaEtapa(?BpmnEtapa $etapa, bool $disabled = false, bool $forcarOpcional = false): array
    {
        if (!$etapa || empty($etapa->campos_formulario)) {
            return [];
        }

        $prefixo = 'dados_formulario.' . self::chaveEtapa($etapa->id) . '.';

        return collect($etapa->campos_formulario)->map(function ($campo) use ($prefixo, $disabled, $forcarOpcional) {
            $tipo = $campo['type'] ?? 'texto';
            $dados = $campo['data'] ?? [];
            $nome = $prefixo . Str::slug($dados['label_campo'] ?? 'campo');
            $obrigatorio = !$disabled && !$forcarOpcional && ($dados['obrigatorio'] ?? false);

            if ($tipo === 'texto') {
                return Forms\Components\TextInput::make($nome)
                    ->label($dados['label_campo'] ?? 'Campo')
                    ->required($obrigatorio)
                    ->disabled($disabled);
            }

            if ($tipo === 'checkbox') {
                $opcoes = $dados['opcoes'] ?? [];
                return Forms\Components\CheckboxList::make($nome)
                    ->label($dados['label_campo'] ?? 'Opções')
                    ->options(array_combine($opcoes, $opcoes) ?: [])
                    ->required($obrigatorio)
                    ->disabled($disabled)
                    ->default([])
                    ->formatStateUsing(fn ($state) => $state === null ? [] : (is_array($state) ? $state : [$state]));
            }

            if ($tipo === 'mapa') {
                // Item 125/210 — seletor de POSIÇÃO no mapa (ponto), distinto da seleção do imóvel.
                return Forms\Components\ViewField::make($nome)
                    ->label($dados['label_campo'] ?? 'Posição no mapa')
                    ->view('filament.forms.components.mapa-ponto')
                    ->viewData(['disabled' => $disabled])
                    ->required($obrigatorio)
                    ->dehydrated(!$disabled);
            }

            if ($tipo === 'documento') {
                $input = Forms\Components\TextInput::make($nome)
                    ->label($dados['label_campo'] ?? 'Documento')
                    ->required($obrigatorio)
                    ->disabled($disabled);

                if (($dados['mascara'] ?? '') === 'cpf') {
                    return $input->mask('999.999.999-99');
                }

                // Telefone híbrido: aceita 8 dígitos (fixo) e 9 dígitos (celular) — item 5
                return $input->mask(RawJs::make(<<<'JS'
                    $input.length > 14 ? '(99) 99999-9999' : '(99) 9999-9999'
                    JS));
            }

            if ($tipo === 'arquivo') {
                // Upload nomeado (item 3) — vira ProcessoAnexo no salvamento (salvarRespostaEtapa).
                return Forms\Components\FileUpload::make($nome)
                    ->label($dados['label_campo'] ?? 'Arquivo')
                    ->required($obrigatorio)
                    ->disabled($disabled)
                    ->directory('processos_anexos')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(5120)
                    ->downloadable()
                    ->openable();
            }

            return null;
        })->filter()->values()->all();
    }

    /**
     * Grava a resposta da etapa como uma nova linha em `processo_respostas` (histórico — item 216).
     * Lê os valores já persistidos em `dados_formulario.etapa_<id>`.
     */
    public static function salvarRespostaEtapa(ProcessoDigital $processo, ?BpmnEtapa $etapa, int $usuarioId): void
    {
        if (!$etapa) {
            return;
        }

        $dados = data_get($processo->dados_formulario, self::chaveEtapa($etapa->id));

        if (empty($dados)) {
            return;
        }

        ProcessoResposta::create([
            'tenant_id' => $processo->tenant_id,
            'processo_digital_id' => $processo->id,
            'bpmn_etapa_id' => $etapa->id,
            'usuario_id' => $usuarioId,
            'dados' => $dados,
        ]);

        self::sincronizarAnexosDeUpload($processo, $etapa, $usuarioId, $dados);
    }

    /**
     * Para cada campo do tipo 'arquivo' da etapa, cria um ProcessoAnexo (item 3) — assim os
     * uploads nomeados aparecem na lista de documentos do processo e podem ser anotados.
     */
    protected static function sincronizarAnexosDeUpload(ProcessoDigital $processo, BpmnEtapa $etapa, int $usuarioId, array $dados): void
    {
        foreach (($etapa->campos_formulario ?? []) as $campo) {
            if (($campo['type'] ?? '') !== 'arquivo') {
                continue;
            }

            $label = $campo['data']['label_campo'] ?? 'Arquivo';
            $caminho = data_get($dados, Str::slug($label));
            if (is_array($caminho)) {
                $caminho = $caminho[0] ?? null;
            }
            if (empty($caminho)) {
                continue;
            }

            $existe = ProcessoAnexo::where('processo_digital_id', $processo->id)
                ->where('caminho_arquivo', $caminho)
                ->exists();
            if ($existe) {
                continue;
            }

            ProcessoAnexo::create([
                'tenant_id' => $processo->tenant_id,
                'processo_digital_id' => $processo->id,
                'etapa_id' => $etapa->id,
                'usuario_id' => $usuarioId,
                'nome_arquivo' => $label . ' — ' . basename($caminho),
                'caminho_arquivo' => $caminho,
                'tipo_anexo' => 'formulario',
            ]);
        }
    }

    /**
     * Avança o processo para a próxima etapa (por ordem) após o preenchimento do SOLICITANTE (item 7).
     * Se a próxima etapa é do analista → status 'em_andamento' (fila do setor); se é do solicitante →
     * 'aguardando_solicitante'; se não há próxima → 'concluido'. Registra a tramitação automática.
     */
    public static function avancarProximaEtapa(ProcessoDigital $processo, int $usuarioId): void
    {
        $atual = $processo->etapaAtual;
        $eraRetorno = (bool) $processo->etapa_retorno_id;
        $destino = self::destinoAposResolver($processo, $atual);

        if ($destino) {
            $processo->update([
                'etapa_atual_id' => $destino->id,
                'etapa_retorno_id' => null, // consumiu o retorno (se havia)
                'analista_id' => null,
                'status' => $destino->executor === 'analista' ? 'em_andamento' : 'aguardando_solicitante',
            ]);

            \App\Models\ProcessoTramitacao::create([
                'tenant_id' => $processo->tenant_id,
                'processo_digital_id' => $processo->id,
                'etapa_origem_id' => $atual?->id,
                'etapa_destino_id' => $destino->id,
                'usuario_id' => $usuarioId,
                'parecer' => $eraRetorno
                    ? 'Pendência resolvida — processo retornado ao setor que solicitou a correção.'
                    : 'Encaminhado automaticamente após o preenchimento do solicitante.',
                'status_parecer' => 'encaminhado',
            ]);
        } else {
            $processo->update(['status' => 'concluido', 'etapa_retorno_id' => null]);

            \App\Models\ProcessoTramitacao::create([
                'tenant_id' => $processo->tenant_id,
                'processo_digital_id' => $processo->id,
                'etapa_origem_id' => $atual?->id,
                'etapa_destino_id' => null,
                'usuario_id' => $usuarioId,
                'parecer' => 'Processo finalizado após o preenchimento do solicitante.',
                'status_parecer' => 'concluido',
            ]);
        }
    }

    /**
     * Para onde ir ao resolver/avançar:
     * - se há `etapa_retorno_id` marcado (reprova pendente resolvida) → volta direto para quem reprovou;
     * - senão → próxima etapa por `ordem`.
     */
    private static function destinoAposResolver(ProcessoDigital $processo, ?BpmnEtapa $atual): ?BpmnEtapa
    {
        if ($processo->etapa_retorno_id) {
            return BpmnEtapa::find($processo->etapa_retorno_id);
        }

        return BpmnEtapa::where('bpmn_fluxo_id', $processo->bpmn_fluxo_id)
            ->when($atual, fn ($q) => $q->where('ordem', '>', $atual->ordem ?? 0))
            ->orderBy('ordem')->orderBy('id')
            ->first();
    }

    /**
     * Julgamento do analista numa etapa (ação única "Avançar Processo" — itens 2/3).
     * - aprovado: avança para a PRÓXIMA etapa por `ordem` (ou conclui, se não houver).
     * - reprovado: volta para a etapa ANTERIOR por `ordem` (se for do solicitante → correção).
     * O roteamento é automático (o analista não escolhe etapa nem usuário destino).
     */
    public static function julgarEtapa(ProcessoDigital $processo, int $usuarioId, string $decisao, ?string $parecer, ?int $etapaDestinoId = null): void
    {
        $atual = $processo->etapaAtual;

        if ($decisao === 'aprovado') {
            // Aprovar: se havia retorno pendente, volta a quem reprovou; senão, próxima por ordem.
            $destino = self::destinoAposResolver($processo, $atual);

            if ($destino) {
                $processo->update([
                    'etapa_atual_id' => $destino->id,
                    'etapa_retorno_id' => null,
                    'analista_id' => null,
                    'status' => $destino->executor === 'analista' ? 'em_andamento' : 'aguardando_solicitante',
                ]);
                $destinoId = $destino->id;
                $statusParecer = 'aprovado';
            } else {
                $processo->update(['status' => 'concluido', 'etapa_retorno_id' => null]);
                $destinoId = null;
                $statusParecer = 'concluido';
            }
        } else {
            // Reprovar: o analista ESCOLHE para onde devolver; marca o retorno = a etapa dele
            // (para o processo voltar direto a ele quando a pendência for resolvida).
            $destino = $etapaDestinoId ? BpmnEtapa::find($etapaDestinoId) : null;
            if (!$destino) {
                // fallback: etapa anterior por ordem
                $destino = BpmnEtapa::where('bpmn_fluxo_id', $processo->bpmn_fluxo_id)
                    ->when($atual, fn ($q) => $q->where('ordem', '<', $atual->ordem ?? 0))
                    ->orderByDesc('ordem')->orderByDesc('id')
                    ->first() ?? $atual;
            }

            $processo->update([
                'etapa_atual_id' => $destino?->id,
                'etapa_retorno_id' => $atual?->id, // ← ao resolver, volta para quem reprovou
                'analista_id' => null,
                'status' => ($destino?->executor === 'solicitante') ? 'pendente_correcao' : 'em_andamento',
            ]);
            $destinoId = $destino?->id;
            $statusParecer = 'reprovado';
        }

        \App\Models\ProcessoTramitacao::create([
            'tenant_id' => $processo->tenant_id,
            'processo_digital_id' => $processo->id,
            'etapa_origem_id' => $atual?->id,
            'etapa_destino_id' => $destinoId,
            'usuario_id' => $usuarioId,
            'parecer' => $parecer ?: '(sem parecer)',
            'status_parecer' => $statusParecer,
        ]);
    }

    /**
     * Resolve os dados do imóvel de um lote para exibição (itens 130/141/221):
     * número do lote, quadra, loteamento, localização e as unidades (inscrição + cadastro imobiliário).
     */
    public static function dadosImovel($loteId): ?array
    {
        if (!$loteId) {
            return null;
        }

        $lote = Lote::withoutGlobalScopes()
            ->with(['quadra.loteamento', 'unidadesImobiliarias'])
            ->find($loteId);

        if (!$lote) {
            return null;
        }

        $localizacao = trim(collect([$lote->tipo_logradouro, $lote->logradouro, $lote->numero_logradouro])
            ->filter()
            ->implode(' '));

        return [
            'numero_lote' => $lote->numero_lote,
            'quadra' => $lote->quadra?->name,
            'loteamento' => $lote->quadra?->loteamento?->name,
            'localizacao' => $localizacao !== '' ? $localizacao : ($lote->cep ? ('CEP ' . $lote->cep) : null),
            'unidades' => $lote->unidadesImobiliarias->map(fn ($u) => [
                'inscricao' => $u->inscricao_imobiliaria,
                'cadastro' => $u->codigo_imovel_tributario,
            ])->all(),
        ];
    }

    /** Renderiza os dados do imóvel como um bloco HTML (para Placeholder / Infolist). */
    public static function dadosImovelHtml($loteId): string
    {
        $d = self::dadosImovel($loteId);

        if (!$d) {
            return '<span class="text-gray-500 italic">Nenhum imóvel selecionado.</span>';
        }

        $linha = fn ($rotulo, $valor) => '<div class="flex justify-between gap-4 py-0.5 border-b border-gray-100 dark:border-gray-800">'
            . '<span class="text-gray-500">' . e($rotulo) . '</span>'
            . '<span class="font-medium text-right">' . e($valor ?: '—') . '</span></div>';

        $html = '<div class="text-sm rounded-lg border border-gray-200 dark:border-gray-700 p-3 space-y-0.5">';
        $html .= $linha('Número do Lote', $d['numero_lote']);
        $html .= $linha('Quadra', $d['quadra']);
        $html .= $linha('Loteamento', $d['loteamento']);
        $html .= $linha('Localização', $d['localizacao']);

        if (!empty($d['unidades'])) {
            $html .= '<div class="pt-2 mt-1"><div class="text-gray-500 mb-1">Unidades Imobiliárias</div>';
            foreach ($d['unidades'] as $u) {
                $html .= '<div class="pl-2 mb-1">'
                    . $linha('Inscrição Imobiliária', $u['inscricao'])
                    . $linha('Cadastro Imobiliário', $u['cadastro'])
                    . '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /** Respostas do formulário agrupadas por etapa (infolist do analista e do cidadão — item 216). */
    public static function respostasHtml(ProcessoDigital $processo): string
    {
        $fluxo = $processo->fluxo;
        $etapas = $fluxo ? $fluxo->etapas()->orderBy('ordem')->orderBy('id')->get() : collect();
        $dados = $processo->dados_formulario ?? [];

        $html = '';
        foreach ($etapas as $etapa) {
            $respostas = $dados['etapa_' . $etapa->id] ?? null;
            if (empty($respostas) || !is_array($respostas)) {
                continue;
            }

            $labels = [];
            $tipos = [];
            foreach (($etapa->campos_formulario ?? []) as $campo) {
                $label = $campo['data']['label_campo'] ?? 'Campo';
                $slug = Str::slug($label);
                $labels[$slug] = $label;
                $tipos[$slug] = $campo['type'] ?? 'texto';
            }

            $linhas = '';
            foreach ($respostas as $slug => $valor) {
                if (($tipos[$slug] ?? '') === 'arquivo') {
                    continue; // arquivos aparecem na seção "Documentos"
                }
                $label = $labels[$slug] ?? $slug;
                $linhas .= '<li><span class="font-medium">' . e($label) . ':</span> ' . e(self::formatarValorResposta($valor)) . '</li>';
            }

            if ($linhas === '') {
                continue;
            }

            $html .= '<div class="mb-4 rounded-lg border border-gray-200 dark:border-gray-700 p-3">';
            $html .= '<div class="font-bold text-primary-600 mb-2">' . e($etapa->nome) . '</div>';
            $html .= '<ul class="space-y-1 text-sm">' . $linhas . '</ul></div>';
        }

        return $html !== '' ? $html : '<span class="text-gray-500 italic">Nenhuma resposta registrada.</span>';
    }

    /** Linha do tempo das tramitações do processo (item 216). */
    public static function historicoHtml(ProcessoDigital $processo): string
    {
        $trs = $processo->tramitacoes()->with('responsavel')->orderBy('created_at')->get();

        if ($trs->isEmpty()) {
            return '<span style="color:#9ca3af; font-style:italic;">Sem tramitações registradas.</span>';
        }

        $etapaNomes = BpmnEtapa::where('bpmn_fluxo_id', $processo->bpmn_fluxo_id)->pluck('nome', 'id');
        $badges = [
            'aprovado' => ['Aprovado', '#16a34a'],
            'reprovado' => ['Reprovado', '#dc2626'],
            'encaminhado' => ['Encaminhado', '#2563eb'],
            'concluido' => ['Concluído', '#16a34a'],
        ];

        // Estilos inline de propósito: o CSS do painel não compila as classes Tailwind arbitrárias
        // desta timeline (a bolinha caía sobre a data).
        $html = '<div style="margin-top:4px;">';
        foreach ($trs as $t) {
            [$lbl, $cor] = $badges[$t->status_parecer] ?? [ucfirst((string) $t->status_parecer), '#6b7280'];
            $origem = $t->etapa_origem_id ? ($etapaNomes[$t->etapa_origem_id] ?? '—') : 'Abertura';
            $destino = $t->etapa_destino_id ? ($etapaNomes[$t->etapa_destino_id] ?? '—') : 'Conclusão';
            $data = $t->created_at?->format('d/m/Y H:i');
            $usuario = $t->responsavel?->name ?? 'Sistema';

            $html .= '<div style="position:relative; margin-left:5px; border-left:2px solid #e5e7eb; padding-left:20px; padding-bottom:16px;">';
            $html .= '<span style="position:absolute; left:-7px; top:2px; width:12px; height:12px; border-radius:9999px; background:' . $cor . '; border:2px solid #ffffff;"></span>';
            $html .= '<div style="font-size:11px; color:#9ca3af;">' . e($data) . '</div>';
            $html .= '<div style="margin-top:2px; font-size:13px;"><span style="display:inline-block; padding:1px 8px; border-radius:6px; color:#ffffff; font-size:11px; background:' . $cor . ';">' . e($lbl) . '</span> <strong>' . e($origem) . ' &rarr; ' . e($destino) . '</strong></div>';
            $html .= '<div style="font-size:11px; color:#9ca3af;">por ' . e($usuario) . '</div>';
            if ($t->parecer) {
                $html .= '<div style="font-size:13px; margin-top:2px; color:#374151;">' . e($t->parecer) . '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /** Lista de documentos anexados ao processo (apenas leitura/download). */
    public static function documentosHtml(ProcessoDigital $processo): string
    {
        $anexos = ProcessoAnexo::where('processo_digital_id', $processo->id)->orderBy('id')->get();

        if ($anexos->isEmpty()) {
            return '<span class="text-gray-500 italic">Nenhum documento anexado.</span>';
        }

        $html = '<ul class="space-y-2">';
        foreach ($anexos as $anexo) {
            $url = \Illuminate\Support\Facades\Storage::url($anexo->caminho_arquivo);
            $icone = str_ends_with(strtolower($anexo->nome_arquivo), '.pdf') ? '📕' : '🖼️';
            $tag = $anexo->tipo_anexo === 'anotado'
                ? " <span class='text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-800'>anotado v{$anexo->versao}</span>"
                : '';
            $html .= "<li><a href='{$url}' target='_blank' class='text-primary-600 hover:text-primary-800 underline font-medium'>{$icone} " . e($anexo->nome_arquivo) . "</a>{$tag}</li>";
        }
        $html .= '</ul>';

        return $html;
    }

    private static function formatarValorResposta($valor): string
    {
        if (is_array($valor)) {
            if (isset($valor['lat'], $valor['lon'])) {
                return number_format((float) $valor['lat'], 5) . ', ' . number_format((float) $valor['lon'], 5);
            }
            return implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $valor));
        }
        return (string) $valor;
    }
}
