<?php

namespace App\Filament\Pages\Traits;

use App\Models\Lote;
use App\Models\UnidadeImobiliaria;
use App\Models\Zona;
use App\Models\Quadra;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

trait HasLoteActions
{
    /**
     * Ação: Criar Novo Lote e Unidade Automática
     */
    public function criarLoteAction(): Action
    {
        return Action::make('criarLote')
            ->modalHeading('Cadastrar Novo Lote')
            ->modalDescription('Preencha os dados básicos. A quadra e a zona já foram detectadas pelo satélite.')
            ->modalSubmitActionLabel('Salvar Lote e Unidade')
            ->modalWidth('md')
            ->fillForm(fn(): array => [
                'quadra_id' => $this->quadraRascunhoId,
            ])
            ->form([
                TextInput::make('numero_lote')
                    ->label('Número do Lote')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        table: 'lotes',
                        column: 'numero_lote',
                        modifyRuleUsing: fn(Unique $rule) => $rule
                            ->where('tenant_id', $this->tenantId)
                            ->where('quadra_id', $this->quadraRascunhoId)
                    )
                    ->validationMessages(['unique' => 'Já existe um Lote com este número nesta exata Quadra.']),

                TextInput::make('main_facade_length')
                    ->label('Testada Principal / Frente (metros)')
                    ->numeric()
                    ->nullable(),

                Select::make('quadra_id')
                    ->label('Quadra (Auto-detectada)')
                    ->options(fn() => Quadra::where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->disabled()
                    ->dehydrated()
                    ->required(),

                TextInput::make('codigo_imovel_tributario')
                    ->label('Código do Imóvel Tributário (Opcional)')
                    ->maxLength(255),

                TextInput::make('inscricao_imobiliaria')
                    ->label('Inscrição Imobiliária Base (Opcional)')
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['zona_id'] = $this->zonaRascunhoId;
                $data['code'] = (string) Str::uuid();

                $lote = Lote::create($data);

                DB::statement("UPDATE lotes SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$lote->id]);

                if ($lote) {
                    $unidade = UnidadeImobiliaria::create([
                        'tenant_id' => $lote->tenant_id,
                        'code' => (string) Str::uuid(),
                        'lote_id' => $lote->id,
                        'codigo_imovel_tributario' => $data['codigo_imovel_tributario'] ?? null,
                        'inscricao_imobiliaria' => $data['inscricao_imobiliaria'] ?? null,
                    ]);

                    if ($unidade) {
                        DB::statement("
                            UPDATE unidade_imobiliarias
                            SET geo = (SELECT ST_PointOnSurface(geo) FROM lotes WHERE id = ?)
                            WHERE id = ?
                        ", [$lote->id, $unidade->id]);
                    }
                }

                Notification::make()->title('Sucesso!')->body('Lote e Unidade Imobiliária cadastrados.')->success()->send();

                $this->dispatch('adicionar-lote-mapa', [
                    'id' => $lote->id,
                    'numero_lote' => $lote->numero_lote,
                    'geo' => $this->geometriaRascunho
                ]);

                $this->geometriaRascunho = null;
            });
    }

    /**
     * Ação: Editar Dados do Lote
     */
    public function editarDadosLoteAction(): Action
    {
        return Action::make('editarDadosLote')
            ->modalHeading('Editar Dados do Lote')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->modalWidth('md')
            ->fillForm(function (): array {
                $lote = Lote::find($this->loteAtivoId);
                return [
                    'numero_lote' => $lote ? $lote->numero_lote : '',
                    'main_facade_length' => $lote ? $lote->main_facade_length : null,
                ];
            })
            ->form([
                TextInput::make('numero_lote')
                    ->label('Número do Lote')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        table: 'lotes',
                        column: 'numero_lote',
                        ignorable: fn() => Lote::find($this->loteAtivoId),
                        modifyRuleUsing: function (Unique $rule) {
                            $lote = Lote::find($this->loteAtivoId);
                            return $rule->where('tenant_id', $this->tenantId)->where('quadra_id', $lote->quadra_id);
                        }
                    )
                    ->validationMessages(['unique' => 'Já existe um Lote com este número nesta exata Quadra.']),

                TextInput::make('main_facade_length')
                    ->label('Testada Principal / Frente (metros)')
                    ->numeric()
                    ->nullable(),
            ])
            ->action(function (array $data) {
                $lote = Lote::find($this->loteAtivoId);
                if ($lote) {
                    $lote->update($data);
                    Notification::make()->title('Lote atualizado!')->success()->send();
                    $this->loteAtivoNome = $data['numero_lote'];
                    $this->loteFacePrincipal = $data['main_facade_length'];
                    $this->dispatch('atualizar-label-lote', ['id' => $lote->id, 'numero_lote' => $data['numero_lote']]);
                }
            });
    }

    /**
     * Ação: Ver Unidades do Lote (A Tabela Base com Blade Dinâmico)
     */
    public function verUnidadesAction(): Action
    {
        return Action::make('verUnidades')
            ->hiddenLabel()
            ->modalHeading(fn() => 'Unidades Imobiliárias - Lote #' . $this->loteAtivoNome)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalWidth('3xl')
            ->modalContent(function () {
                $unidades = UnidadeImobiliaria::where('lote_id', $this->loteAtivoId)->get();

                // Usamos HEREDOC (<<<BLADE) para não precisar escapar aspas no HTML e chamar botões Livewire com facilidade
                $bladeView = <<<'BLADE'
                    <div>
                        <div class="mb-4 flex justify-end">
                            {{-- Usando o botão nativo do Filament com a ação de REPLACE --}}
                            <x-filament::button 
                                wire:click="replaceMountedAction('criarUnidadeAction')" 
                                color="success" 
                                icon="heroicon-m-plus">
                                Nova Unidade
                            </x-filament::button>
                        </div>

                        @if($unidades->isEmpty())
                            <div class="text-center py-8 text-gray-500 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-dashed border-gray-300 dark:border-gray-700">
                                <x-heroicon-o-home-modern class="w-10 h-10 mx-auto text-gray-400 mb-2 opacity-50" />
                                Nenhuma unidade cadastrada neste lote.
                            </div>
                        @else
                            <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                                <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                                    <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-700 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700">
                                        <tr>
                                            <th class="px-4 py-3">Inscrição</th>
                                            <th class="px-4 py-3">Cód Tributário</th>
                                            <th class="px-4 py-3">Proprietário (ID)</th>
                                            <th class="px-4 py-3 text-right"><span class="sr-only">Ações</span></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($unidades as $u)
                                            {{-- Ao clicar em qualquer lugar da linha, abre a edição --}}
                                            <tr wire:click="replaceMountedAction('editarUnidadeAction', { unidadeId: {{ $u->id }} })" 
                                                class="cursor-pointer bg-white dark:bg-gray-900 hover:bg-primary-50 dark:hover:bg-gray-800 transition-colors group">
                                                
                                                <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">{{ $u->inscricao_imobiliaria ?? 'N/A' }}</td>
                                                <td class="px-4 py-3">{{ $u->codigo_imovel_tributario ?? 'N/A' }}</td>
                                                <td class="px-4 py-3">{{ $u->proprietario_id ? 'ID: ' . $u->proprietario_id : 'Não informado' }}</td>
                                                <td class="px-4 py-3 text-right">
                                                    <x-heroicon-o-pencil-square class="w-5 h-5 text-gray-400 group-hover:text-primary-600 inline-block transition-colors" />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                BLADE;

                return new HtmlString(Blade::render($bladeView, ['unidades' => $unidades]));
            });
    }

    /**
     * Ação: Criar Nova Unidade Imobiliária
     */
    public function criarUnidadeAction(): Action
    {
        return Action::make('criarUnidadeAction')
            ->modalHeading('Cadastrar Nova Unidade Imobiliária')
            ->modalSubmitActionLabel('Salvar Unidade')
            ->modalWidth('lg')
            ->form([
                TextInput::make('inscricao_imobiliaria')
                    ->label('Inscrição Imobiliária')
                    ->maxLength(255)
                    ->unique(
                        table: 'unidade_imobiliarias',
                        column: 'inscricao_imobiliaria',
                        modifyRuleUsing: fn(\Illuminate\Validation\Rules\Unique $rule) => $rule->where('tenant_id', $this->tenantId)
                    )
                    ->validationMessages([
                        'unique' => 'Esta Inscrição Imobiliária já está cadastrada neste município.',
                    ]),

                TextInput::make('codigo_imovel_tributario')
                    ->label('Código do Imóvel Tributário')
                    ->maxLength(255)
                    ->unique(
                        table: 'unidade_imobiliarias',
                        column: 'codigo_imovel_tributario',
                        modifyRuleUsing: fn(\Illuminate\Validation\Rules\Unique $rule) => $rule->where('tenant_id', $this->tenantId)
                    )
                    ->validationMessages([
                        'unique' => 'Este Código Tributário já está cadastrado neste município.',
                    ]),

                TextInput::make('proprietario_id')
                    ->label('ID do Proprietário (Temporário)')
                    ->numeric()
                    ->nullable(),
            ])
            ->action(function (array $data) {
                $lote = Lote::find($this->loteAtivoId);

                $data['tenant_id'] = $this->tenantId;
                $data['lote_id'] = $this->loteAtivoId;
                $data['code'] = (string) Str::uuid();

                $unidade = UnidadeImobiliaria::create($data);

                // Garante que a unidade pegue o ponto (centroide) do lote
                if ($unidade && $lote) {
                    DB::statement("
                        UPDATE unidade_imobiliarias
                        SET geo = (SELECT ST_PointOnSurface(geo) FROM lotes WHERE id = ?)
                        WHERE id = ?
                    ", [$lote->id, $unidade->id]);
                }

                Notification::make()->title('Unidade criada com sucesso!')->success()->send();

                // Substitui a modal atual voltando para a lista de unidades
                $this->replaceMountedAction('verUnidades');
            })
            // Se o usuário desistir de criar e fechar a modal, voltamos para a tabela de unidades
            ->modalCancelAction(false)
            ->extraModalFooterActions([
                Action::make('voltar')
                    ->label('Voltar')
                    ->color('gray') // Fica com a mesma cor neutra do Cancelar
                    ->action(fn() => $this->replaceMountedAction('verUnidades')),
            ]);
    }

    /**
     * Ação: Editar Unidade Imobiliária (Agora com botão de Excluir dentro)
     */
    public function editarUnidadeAction(): Action
    {
        return Action::make('editarUnidadeAction')
            ->modalHeading('Editar Unidade Imobiliária')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->modalWidth('lg')
            ->fillForm(function (array $arguments): array {
                if (!isset($arguments['unidadeId']))
                    return [];
                $unidade = UnidadeImobiliaria::find($arguments['unidadeId']);
                return $unidade ? $unidade->toArray() : [];
            })
            // 🛑 MÁGICA: Transformamos o form em uma função que recebe os argumentos
            ->form(function (array $arguments) {
                // Buscamos a unidade que está sendo editada ANTES de montar os campos
                $unidade = \App\Models\UnidadeImobiliaria::find($arguments['unidadeId'] ?? null);

                return [
                    TextInput::make('inscricao_imobiliaria')
                        ->label('Inscrição Imobiliária')
                        ->maxLength(255)
                        ->unique(
                            table: 'unidade_imobiliarias',
                            column: 'inscricao_imobiliaria',
                            ignorable: $unidade, // Passamos o modelo diretamente aqui!
                            modifyRuleUsing: fn(\Illuminate\Validation\Rules\Unique $rule) => $rule->where('tenant_id', $this->tenantId)
                        )
                        ->validationMessages([
                            'unique' => 'Esta Inscrição Imobiliária já está cadastrada neste município.',
                        ]),

                    TextInput::make('codigo_imovel_tributario')
                        ->label('Código do Imóvel Tributário')
                        ->maxLength(255)
                        ->unique(
                            table: 'unidade_imobiliarias',
                            column: 'codigo_imovel_tributario',
                            ignorable: $unidade, // Passamos o modelo diretamente aqui!
                            modifyRuleUsing: fn(\Illuminate\Validation\Rules\Unique $rule) => $rule->where('tenant_id', $this->tenantId)
                        )
                        ->validationMessages([
                            'unique' => 'Este Código Tributário já está cadastrado neste município.',
                        ]),

                    TextInput::make('proprietario_id')
                        ->label('ID do Proprietário (Temporário)')
                        ->numeric()
                        ->nullable(),
                ];
            })
            ->action(function (array $data, array $arguments) {
                $unidade = UnidadeImobiliaria::find($arguments['unidadeId']);
                if ($unidade) {
                    $unidade->update($data);
                    Notification::make()->title('Unidade atualizada!')->success()->send();
                }

                // Volta para a lista
                $this->replaceMountedAction('verUnidades');
            })
            ->modalCancelAction(false)
            ->extraModalFooterActions(function (array $arguments) {
                $unidadeId = $arguments['unidadeId'] ?? null;

                return [


                    Action::make('excluir_unidade')
                        ->label('Excluir Unidade')
                        ->color('danger')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Excluir Unidade Imobiliária')
                        ->modalDescription('Tem certeza que deseja excluir esta unidade? A ação enviará o registro para a lixeira.')
                        ->action(function () use ($unidadeId) {
                            if ($unidadeId) {
                                $unidade = UnidadeImobiliaria::find($unidadeId);
                                if ($unidade) {
                                    $unidade->delete();
                                    Notification::make()->title('Unidade removida!')->success()->send();
                                }
                            }
                            $this->replaceMountedAction('verUnidades');
                        }),

                    Action::make('voltar')
                        ->label('Voltar')
                        ->color('gray')
                        ->action(fn() => $this->replaceMountedAction('verUnidades')),
                ];
            });
    }

    /**
     * Ação: Passo 1 - Selecionar CNAEs
     */
    public function consultarViabilidadeAction(): Action
    {
        return Action::make('consultarViabilidadeAction')
            ->modalHeading('Consulta de Viabilidade')
            ->modalDescription('Selecione as atividades desejadas para analisar a viabilidade neste lote.')
            ->modalSubmitActionLabel('Consultar Viabilidade')
            ->modalWidth('lg')
            ->closeModalByClickingAway(false)
            ->form([
                Select::make('cnaes')
                    ->label('Atividades Econômicas (CNAE)')
                    ->multiple()
                    ->searchable()
                    ->placeholder('Digite o código ou nome da atividade...')
                    // MÁGICA: Busca assíncrona no banco conforme o usuário digita
                    ->getSearchResultsUsing(function (string $search) {
                        $searchClean = preg_replace('/[^0-9a-zA-Z\s]/', '', $search);
                        return \App\Models\Cnae::where('tenant_id', $this->tenantId)
                            ->where(function ($q) use ($search, $searchClean) {
                                $q->where('codigo', 'like', "%{$search}%")
                                    ->orWhereRaw("REGEXP_REPLACE(codigo, '[^0-9]', '') like ?", ["%{$searchClean}%"])
                                    ->orWhere('descricao', 'ilike', "%{$search}%");
                            })
                            ->limit(30)
                            ->get()
                            ->mapWithKeys(fn($cnae) => [$cnae->codigo => $cnae->codigo . ' - ' . $cnae->descricao])
                            ->toArray();
                    })
                    // Necessário para o Filament saber renderizar as opções já salvas/selecionadas
                    ->getOptionLabelsUsing(function (array $values) {
                        return \App\Models\Cnae::whereIn('codigo', $values)
                            ->get()
                            ->mapWithKeys(fn($cnae) => [$cnae->codigo => $cnae->codigo . ' - ' . $cnae->descricao])
                            ->toArray();
                    })
                    ->required()
            ])
            ->action(function (array $data) {
                // Avança para a modal de resultado passando a lista de CNAEs selecionada
                $this->replaceMountedAction('resultadoViabilidadeAction', ['cnaes' => $data['cnaes']]);
            });
    }

    /**
     * Ação: Passo 2 - Mostrar Resultado em Tabela
     */
    public function resultadoViabilidadeAction(): Action
    {
        return Action::make('resultadoViabilidadeAction')
            ->modalHeading('Resultado da Análise')
            ->modalWidth('5xl')
            ->closeModalByClickingAway(false)
            ->modalSubmitAction(false)
            ->modalContent(function (array $arguments) {
                $cnaes = $arguments['cnaes'] ?? [];

                $service = app(\App\Services\Viabilidade\ViabilidadeService::class);
                $analise = $service->analisar($this->loteAtivoId, $cnaes);

                if (isset($analise['error'])) {
                    return new \Illuminate\Support\HtmlString("<div class='text-red-500 font-bold p-4 bg-red-50 rounded-lg'>Erro: {$analise['error']}</div>");
                }

                // 🛑 Usando <x-filament::badge> as cores NUNCA mais vão sumir
                $bladeView = <<<'BLADE'
                    <div class="space-y-4 font-sans">
                        <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                            <h3 class="font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider text-sm">Parecer Técnico</h3>
                            <x-filament::badge color="info" size="lg">
                                Zona: {{ $analise['zona']['sigla'] }} - {{ $analise['zona']['nome'] }}
                            </x-filament::badge>
                        </div>
                        
                        <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                            <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                                <thead class="bg-gray-100 dark:bg-gray-800/80 text-xs uppercase text-gray-700 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="px-4 py-4">CNAE</th>
                                        <th class="px-4 py-4 w-1/2">Descrição da Atividade</th>
                                        <th class="px-4 py-4">Classificações</th>
                                        <th class="px-4 py-4 text-center">Viabilidade</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                                    @foreach($analise['analises'] as $item)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                            <td class="px-4 py-4 font-bold text-gray-900 dark:text-white whitespace-nowrap">{{ $item['cnae'] }}</td>
                                            <td class="px-4 py-4 text-xs leading-relaxed">{{ $item['descricao'] }}</td>
                                            <td class="px-4 py-4 text-xs">
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($item['classificacoes_detalhe'] as $detalhe)
                                                        @php
                                                            $color = match($detalhe['status']) {
                                                                'permitido' => 'success',
                                                                'permissivel' => 'warning',
                                                                'proibido' => 'danger',
                                                                default => 'gray'
                                                            };
                                                        @endphp
                                                        <x-filament::badge :color="$color" size="sm" title="{{ ucfirst($detalhe['status']) }}">
                                                            {{ $detalhe['classificacao'] }}
                                                        </x-filament::badge>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 text-center">
                                                @php
                                                    $badge = match($item['status_final']) {
                                                        'permitido' => 'success',
                                                        'permissivel' => 'warning',
                                                        'proibido' => 'danger',
                                                        default => 'gray'
                                                    };
                                                @endphp
                                                <x-filament::badge :color="$badge" size="lg" class="font-black uppercase tracking-wider">
                                                    {{ $item['status_final'] }}
                                                </x-filament::badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                BLADE;

                return new \Illuminate\Support\HtmlString(\Illuminate\Support\Facades\Blade::render($bladeView, ['analise' => $analise]));
            })
           ->extraModalFooterActions(function (array $arguments) {
                // Junta os CNAEs em uma string simples (Ex: "47.51-2,47.52-1")
                $cnaesString = implode(',', $arguments['cnaes'] ?? []);
                
                return [
                    Action::make('voltar')
                        ->label('Nova Consulta')
                        ->color('gray')
                        ->action(fn () => $this->replaceMountedAction('consultarViabilidadeAction')),

                    Action::make('imprimir_pdf')
                        ->label('Imprimir Relatório')
                        ->color('success')
                        ->icon('heroicon-o-printer')
                        ->extraAttributes([
                            'id' => 'btn-imprimir-viab',
                            'type' => 'button',
                            // Guarda a string de forma segura no HTML
                            'data-cnaes' => $cnaesString, 
                            // 🛑 O TRUQUE: O Alpine lê a variável do HTML sem usar nenhuma aspa!
                            'x-on:click.prevent' => "capturarMapaEImprimir({$this->loteAtivoId}, \$el.dataset.cnaes)"
                        ])
                        ->action(function() { /* Vazio propositalmente */ })
                ];
            });
    }

    /**
     * Ação Global: Recebe a Imagem do Mapa via JS e devolve o PDF
     */
    public function imprimirViabilidade($mapImageBase64, $cnaes, $loteId)
    {
        $service = app(\App\Services\Viabilidade\ViabilidadeService::class);
        $dadosAnalise = $service->analisar($loteId, $cnaes);

        if (isset($dadosAnalise['error'])) {
            \Filament\Notifications\Notification::make()->danger()->title('Erro')->body($dadosAnalise['error'])->send();
            return;
        }

        // 🛑 A MÁGICA: Injetamos o número do lote diretamente no array usando o estado atual do Livewire!
        $dadosAnalise['numero_lote'] = $this->loteAtivoNome ?? 'S/N';

        // Chama o Serviço de PDF e devolve o Stream direto pro navegador baixar!
        $pdfService = app(\App\Services\Viabilidade\ViabilidadePdfService::class);
        return $pdfService->generatePdf($dadosAnalise, $mapImageBase64);
    }
}