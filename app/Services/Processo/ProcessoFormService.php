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
        return 'etapa_'.$etapaId;
    }

    /**
     * Monta os componentes Filament do formulário de uma etapa.
     *
     * @param  bool  $disabled  Trava os campos (item 129/220 — B16) ou modo leitura.
     * @param  ?ProcessoDigital  $processo  PD-2 — quando informado, os campos 'arquivo' refletem o
     *                                      checklist de análise: anexo APROVADO fica travado; REPROVADO
     *                                      mostra o motivo e exige substituição.
     * @param  bool  $somenteReprovados  Na correção: renderiza APENAS os campos de arquivo cujo anexo
     *                                   foi reprovado no checklist (foco na pendência). Sem nenhum item
     *                                   reprovado nesta etapa, a etapa inteira aparece (reprova geral).
     * @return array<\Filament\Forms\Components\Component>
     */
    public static function camposDaEtapa(?BpmnEtapa $etapa, bool $disabled = false, bool $forcarOpcional = false, ?ProcessoDigital $processo = null, bool $somenteReprovados = false): array
    {
        if (! $etapa || empty($etapa->campos_formulario)) {
            return [];
        }

        $prefixo = 'dados_formulario.'.self::chaveEtapa($etapa->id).'.';

        $definicoes = collect($etapa->campos_formulario);

        // PD-2 — correção focada: havendo QUALQUER item reprovado no checklist, o cidadão vê
        // apenas os campos com anexo reprovado desta etapa — pode resultar em NENHUM campo
        // (ex.: só o requerimento assinado foi reprovado; a seção dele orienta o reenvio).
        // Sem nenhum item reprovado (reprova geral via parecer), a etapa inteira aparece.
        // Os valores dos demais campos permanecem no state (fill do record) — as condições de
        // visibilidade (PD-3) continuam avaliando gatilhos mesmo sem o componente na tela.
        if ($somenteReprovados && $processo && ProcessoChecklistService::reprovadosPendentes($processo)->isNotEmpty()) {
            $definicoes = $definicoes->filter(function ($campo) use ($processo, $etapa) {
                if (($campo['type'] ?? '') !== 'arquivo') {
                    return false;
                }
                $label = $campo['data']['label_campo'] ?? 'Arquivo';

                // PD-6 — campo múltiplo: entra na correção se QUALQUER arquivo dele foi reprovado.
                return ProcessoChecklistService::anexosVigentesDoCampo($processo, $etapa->id, Str::slug($label), $label)
                    ->contains(fn ($a) => $a->status_analise === 'reprovado');
            })->values();
        }

        return $definicoes->map(function ($campo) use ($prefixo, $disabled, $forcarOpcional, $etapa, $processo) {
            $tipo = $campo['type'] ?? 'texto';
            $dados = $campo['data'] ?? [];
            $nome = $prefixo.Str::slug($dados['label_campo'] ?? 'campo');
            $obrigatorio = ! $disabled && ! $forcarOpcional && ($dados['obrigatorio'] ?? false);

            $component = null;

            if ($tipo === 'texto') {
                $component = Forms\Components\TextInput::make($nome)
                    ->label($dados['label_campo'] ?? 'Campo')
                    ->required($obrigatorio)
                    ->disabled($disabled);
            } elseif ($tipo === 'selecao') {
                // PD-3 — escolha única (ex.: Estado Civil); live() para as condições reagirem na hora.
                $opcoes = $dados['opcoes'] ?? [];

                $component = Forms\Components\Select::make($nome)
                    ->label($dados['label_campo'] ?? 'Seleção')
                    ->options(array_combine($opcoes, $opcoes) ?: [])
                    ->required($obrigatorio)
                    ->disabled($disabled)
                    ->native(false)
                    ->live();
            } elseif ($tipo === 'checkbox') {
                $opcoes = $dados['opcoes'] ?? [];

                // Múltipla escolha como Select multiple — o CheckboxList apresentava bug de
                // seleção (marcar uma opção marcava todas) com o state path aninhado.
                $component = Forms\Components\Select::make($nome)
                    ->label($dados['label_campo'] ?? 'Opções')
                    ->options(array_combine($opcoes, $opcoes) ?: [])
                    ->multiple()
                    ->native(false)
                    ->required($obrigatorio)
                    ->disabled($disabled)
                    ->default([])
                    ->live() // PD-3 — pode ser gatilho de condição
                    ->formatStateUsing(fn ($state) => $state === null ? [] : (is_array($state) ? $state : [$state]));
            } elseif ($tipo === 'mapa') {
                // Item 125/210 — seletor de POSIÇÃO no mapa (ponto), distinto da seleção do imóvel.
                $component = Forms\Components\ViewField::make($nome)
                    ->label($dados['label_campo'] ?? 'Posição no mapa')
                    ->view('filament.forms.components.mapa-ponto')
                    ->viewData(['disabled' => $disabled])
                    ->required($obrigatorio)
                    ->dehydrated(! $disabled);
            } elseif ($tipo === 'documento') {
                $component = Forms\Components\TextInput::make($nome)
                    ->label($dados['label_campo'] ?? 'Documento')
                    ->required($obrigatorio)
                    ->disabled($disabled);

                $mascara = $dados['mascara'] ?? '';
                if ($mascara === 'cpf') {
                    $component->mask('999.999.999-99');
                } elseif ($mascara === 'cnpj') {
                    $component->mask('99.999.999/9999-99');
                } elseif ($mascara === 'cpf_cnpj') {
                    // Dinâmica: CPF (11 dígitos) vira CNPJ (14) ao passar do 12º dígito
                    $component->mask(RawJs::make(<<<'JS'
                        $input.length > 14 ? '99.999.999/9999-99' : '999.999.999-99'
                        JS))->maxLength(18);
                } else {
                    // Telefone híbrido: aceita 8 dígitos (fixo) e 9 dígitos (celular) — item 5
                    $component->mask(RawJs::make(<<<'JS'
                        $input.length > 14 ? '(99) 99999-9999' : '(99) 9999-9999'
                        JS));
                }
            } elseif ($tipo === 'arquivo') {
                // Upload nomeado (item 3) — vira ProcessoAnexo no salvamento (salvarRespostaEtapa).
                // PD-6 — 'multiplo': N arquivos no mesmo campo, cada um vira um anexo próprio.
                $label = $dados['label_campo'] ?? 'Arquivo';
                $multiplo = (bool) ($dados['multiplo'] ?? false);

                $component = Forms\Components\FileUpload::make($nome)
                    ->label($label)
                    ->required($obrigatorio)
                    ->disabled($disabled)
                    ->directory('processos_anexos')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(20480) // 20MB — plantas/projetos grandes (Livewire aceita até 50MB; ver config/livewire.php)
                    ->downloadable()
                    ->openable();

                if ($multiplo) {
                    $component->multiple()->maxFiles(max(2, (int) ($dados['max_arquivos'] ?? 10)));
                }

                // PD-2 — correção por item: reflete o checklist de análise do(s) anexo(s) do campo.
                // Campo múltiplo: trava só com TODOS aprovados; reprovado em QUALQUER arquivo → corrigir.
                if ($processo) {
                    $anexosCampo = ProcessoChecklistService::anexosVigentesDoCampo($processo, $etapa->id, Str::slug($label), $label);
                    $reprovados = $anexosCampo->where('status_analise', 'reprovado');

                    if ($anexosCampo->isNotEmpty() && $reprovados->isEmpty()
                        && $anexosCampo->every(fn ($a) => $a->status_analise === 'aprovado')) {
                        // disabled + dehydrated(true): trava a troca SEM apagar o path já salvo
                        // em dados_formulario (dehydrated(false) apagaria o valor no save).
                        $component->disabled()->dehydrated(true)
                            ->helperText($anexosCampo->count() > 1
                                ? '✅ Todos os arquivos deste campo foram aprovados — não é necessário reenviar.'
                                : '✅ Documento aprovado pela prefeitura — não é necessário reenviar.');
                    } elseif ($reprovados->isNotEmpty()) {
                        $motivos = $reprovados
                            ->map(fn ($a) => basename($a->caminho_arquivo).': '.($a->observacao_analise ?: 'documento incorreto'))
                            ->implode(' · ');

                        $component->required(! $disabled)
                            ->hint($reprovados->count() > 1 ? 'Arquivos reprovados' : 'Documento reprovado')
                            ->hintColor('danger')
                            ->helperText('Motivo da reprovação — '.$motivos.'. '
                                .($multiplo ? 'Remova o(s) arquivo(s) reprovado(s) e anexe a versão corrigida.' : 'Anexe a versão corrigida.'));
                    }
                }
            }

            if (! $component) {
                return null;
            }

            // PD-3 — exibição condicional: "mostrar somente se o campo X tiver o valor Y".
            // Campo oculto no Filament não é validado nem desidratado — o obrigatório só vale visível.
            $condCampo = $dados['visivel_se_campo'] ?? null;
            $condValor = $dados['visivel_se_valor'] ?? null;
            if (filled($condCampo) && filled($condValor)) {
                $caminhoGatilho = $prefixo.$condCampo;

                $component->visible(function (Forms\Get $get) use ($caminhoGatilho, $condValor) {
                    $atual = $get($caminhoGatilho);

                    return is_array($atual)
                        ? in_array($condValor, $atual)          // gatilho checkbox: contém a opção
                        : (string) $atual === (string) $condValor; // gatilho seleção única: igual
                });
            }

            return $component;
        })->filter()->values()->all();
    }

    /**
     * Grava a resposta da etapa como uma nova linha em `processo_respostas` (histórico — item 216).
     * Lê os valores já persistidos em `dados_formulario.etapa_<id>`.
     */
    public static function salvarRespostaEtapa(ProcessoDigital $processo, ?BpmnEtapa $etapa, int $usuarioId): void
    {
        if (! $etapa) {
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
            $slug = Str::slug($label);
            $multiplo = (bool) ($campo['data']['multiplo'] ?? false);

            // Estado do FileUpload: string (único) ou array de paths (múltiplo)
            $valor = data_get($dados, $slug);
            $caminhos = array_values(array_filter(is_array($valor) ? $valor : [$valor]));

            // Campo ÚNICO sem múltiplo: mantém o comportamento original (1º path + cadeia de versões)
            if (! $multiplo) {
                $caminhos = array_slice($caminhos, 0, 1);
            }

            foreach ($caminhos as $caminho) {
                $existe = ProcessoAnexo::where('processo_digital_id', $processo->id)
                    ->where('caminho_arquivo', $caminho)
                    ->exists();
                if ($existe) {
                    continue;
                }

                // PD-2 — campo único: substituição vira NOVA VERSÃO na cadeia do campo.
                // PD-6 — campo múltiplo: cada arquivo é um anexo independente (sem cadeia);
                // em ambos, status_analise nasce 'pendente' pelo default.
                $anterior = $multiplo ? null : ProcessoChecklistService::ultimoAnexoDoCampo($processo, $etapa->id, $slug, $label);

                ProcessoAnexo::create([
                    'tenant_id' => $processo->tenant_id,
                    'processo_digital_id' => $processo->id,
                    'etapa_id' => $etapa->id,
                    'campo_slug' => $slug,
                    'usuario_id' => $usuarioId,
                    'nome_arquivo' => $label.' — '.basename($caminho),
                    'caminho_arquivo' => $caminho,
                    'tipo_anexo' => 'formulario',
                    'versao' => $anterior ? $anterior->versao + 1 : 1,
                    'anexo_origem_id' => $anterior?->cadeiaId(),
                ]);
            }

            // PD-6 — reconciliação do campo múltiplo: arquivo REMOVIDO da lista pelo cidadão
            // (ex.: trocou a folha reprovada) é soft-deletado — sai do checklist e deixa de
            // bloquear. Anexos APROVADOS nunca são removidos (o campo aprovado fica travado).
            if ($multiplo) {
                ProcessoAnexo::withoutGlobalScopes()
                    ->where('processo_digital_id', $processo->id)
                    ->where('etapa_id', $etapa->id)
                    ->where('tipo_anexo', 'formulario')
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($slug, $label) {
                        $q->where('campo_slug', $slug)
                            ->orWhere(fn ($legado) => $legado->whereNull('campo_slug')->where('nome_arquivo', 'like', $label.' — %'));
                    })
                    ->whereNotIn('caminho_arquivo', $caminhos ?: ['—nenhum—'])
                    ->where('status_analise', '!=', 'aprovado')
                    ->get()
                    ->each
                    ->delete();
            }
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

            $statusParecer = 'encaminhado';
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

            $statusParecer = 'concluido';
        }

        // Notifica o cidadão por e-mail (best-effort — nunca quebra a tramitação).
        ProcessoNotificacaoService::notificarTransicao($processo, $statusParecer, null);
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
            if (! $destino) {
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

        // Notifica o cidadão por e-mail (best-effort — nunca quebra a tramitação).
        ProcessoNotificacaoService::notificarTransicao($processo, $statusParecer, $parecer);
    }

    /**
     * Resolve os dados do imóvel de um lote para exibição (itens 130/141/221):
     * número do lote, quadra, loteamento, localização e as unidades (inscrição + cadastro imobiliário).
     */
    public static function dadosImovel($loteId): ?array
    {
        if (! $loteId) {
            return null;
        }

        $lote = Lote::withoutGlobalScopes()
            ->with(['quadra.loteamento', 'unidadesImobiliarias'])
            ->find($loteId);

        if (! $lote) {
            return null;
        }

        $localizacao = trim(collect([$lote->tipo_logradouro, $lote->logradouro, $lote->numero_logradouro])
            ->filter()
            ->implode(' '));

        return [
            'numero_lote' => $lote->numero_lote,
            'quadra' => $lote->quadra?->name,
            'loteamento' => $lote->quadra?->loteamento?->name,
            'localizacao' => $localizacao !== '' ? $localizacao : ($lote->cep ? ('CEP '.$lote->cep) : null),
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

        if (! $d) {
            return '<span class="text-gray-500 italic">Nenhum imóvel selecionado.</span>';
        }

        $linha = fn ($rotulo, $valor) => '<div class="flex justify-between gap-4 py-0.5 border-b border-gray-100 dark:border-gray-800">'
            .'<span class="text-gray-500">'.e($rotulo).'</span>'
            .'<span class="font-medium text-right">'.e($valor ?: '—').'</span></div>';

        $html = '<div class="text-sm rounded-lg border border-gray-200 dark:border-gray-700 p-3 space-y-0.5">';
        $html .= $linha('Número do Lote', $d['numero_lote']);
        $html .= $linha('Quadra', $d['quadra']);
        $html .= $linha('Loteamento', $d['loteamento']);
        $html .= $linha('Localização', $d['localizacao']);

        if (! empty($d['unidades'])) {
            $html .= '<div class="pt-2 mt-1"><div class="text-gray-500 mb-1">Unidades Imobiliárias</div>';
            foreach ($d['unidades'] as $u) {
                $html .= '<div class="pl-2 mb-1">'
                    .$linha('Inscrição Imobiliária', $u['inscricao'])
                    .$linha('Cadastro Imobiliário', $u['cadastro'])
                    .'</div>';
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
            $respostas = $dados['etapa_'.$etapa->id] ?? null;
            if (empty($respostas) || ! is_array($respostas)) {
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
                $linhas .= '<li><span class="font-medium">'.e($label).':</span> '.e(self::formatarValorResposta($valor)).'</li>';
            }

            if ($linhas === '') {
                continue;
            }

            $html .= '<div class="mb-4 rounded-lg border border-gray-200 dark:border-gray-700 p-3">';
            $html .= '<div class="font-bold text-primary-600 mb-2">'.e($etapa->nome).'</div>';
            $html .= '<ul class="space-y-1 text-sm">'.$linhas.'</ul></div>';
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
            $html .= '<span style="position:absolute; left:-7px; top:2px; width:12px; height:12px; border-radius:9999px; background:'.$cor.'; border:2px solid #ffffff;"></span>';
            $html .= '<div style="font-size:11px; color:#9ca3af;">'.e($data).'</div>';
            $html .= '<div style="margin-top:2px; font-size:13px;"><span style="display:inline-block; padding:1px 8px; border-radius:6px; color:#ffffff; font-size:11px; background:'.$cor.';">'.e($lbl).'</span> <strong>'.e($origem).' &rarr; '.e($destino).'</strong></div>';
            $html .= '<div style="font-size:11px; color:#9ca3af;">por '.e($usuario).'</div>';
            if ($t->parecer) {
                $html .= '<div style="font-size:13px; margin-top:2px; color:#374151;">'.e($t->parecer).'</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /** Lista de documentos anexados ao processo (apenas leitura/download), com o status da análise (PD-2). */
    public static function documentosHtml(ProcessoDigital $processo): string
    {
        // PD-1 — o 'requerimento_gerado' é artefato intermediário (fica no banco p/ auditoria,
        // mas não polui a lista): só o requerimento ASSINADO pelo solicitante aparece.
        $anexos = ProcessoAnexo::where('processo_digital_id', $processo->id)
            ->where('tipo_anexo', '!=', 'requerimento_gerado')
            ->orderBy('id')
            ->get();

        if ($anexos->isEmpty()) {
            return '<span class="text-gray-500 italic">Nenhum documento anexado.</span>';
        }

        $analisaveis = ProcessoChecklistService::anexosAnalisaveis($processo);
        $analisaveisIds = $analisaveis->pluck('id');
        $ultimas = ProcessoChecklistService::ultimasVersoes($analisaveis);

        $html = '<ul class="space-y-2">';
        foreach ($anexos as $anexo) {
            $url = \Illuminate\Support\Facades\Storage::url($anexo->caminho_arquivo);
            $icone = str_ends_with(strtolower($anexo->nome_arquivo), '.pdf') ? '📕' : '🖼️';

            $tag = match ($anexo->tipo_anexo) {
                'anotado' => " <span class='text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-800'>anotado v{$anexo->versao}</span>",
                'requerimento_gerado' => " <span class='text-xs px-1.5 py-0.5 rounded bg-blue-100 text-blue-800'>requerimento gerado v{$anexo->versao}</span>",
                'requerimento_assinado' => " <span class='text-xs px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-800'>assinado v{$anexo->versao}</span>",
                default => '',
            };

            // PD-2 — badge do checklist: só a última versão da cadeia carrega o status vigente
            if ($analisaveisIds->contains($anexo->id)) {
                if (! $ultimas->contains($anexo->id)) {
                    $tag .= " <span class='text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600'>substituído</span>";
                } else {
                    $tag .= match ($anexo->status_analise) {
                        'aprovado' => " <span class='text-xs px-1.5 py-0.5 rounded bg-green-100 text-green-800'>✔ aprovado</span>",
                        'reprovado' => " <span class='text-xs px-1.5 py-0.5 rounded bg-red-100 text-red-800'>✖ reprovado</span>"
                            .($anexo->observacao_analise ? " <span class='text-xs text-red-700'>".e($anexo->observacao_analise).'</span>' : ''),
                        default => " <span class='text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600'>aguardando análise</span>",
                    };
                }
            }

            $html .= "<li><a href='{$url}' target='_blank' class='text-primary-600 hover:text-primary-800 underline font-medium'>{$icone} ".e($anexo->nome_arquivo)."</a>{$tag}</li>";
        }
        $html .= '</ul>';

        return $html;
    }

    private static function formatarValorResposta($valor): string
    {
        if (is_array($valor)) {
            if (isset($valor['lat'], $valor['lon'])) {
                return number_format((float) $valor['lat'], 5).', '.number_format((float) $valor['lon'], 5);
            }

            return implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $valor));
        }

        return (string) $valor;
    }
}
