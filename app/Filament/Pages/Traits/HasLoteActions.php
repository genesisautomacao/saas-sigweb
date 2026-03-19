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
            ->modalWidth('4xl')
            ->modalContent(function () {
                $unidades = UnidadeImobiliaria::where('lote_id', $this->loteAtivoId)->get();

                $bladeView = <<<'BLADE'
                    <div>
                        <div class="mb-4 flex justify-end gap-2">
                            {{-- 🛑 NOVO: Botão de Sincronização em Massa (Bulk) --}}
                            @if($unidades->isNotEmpty())
                                <x-filament::button 
                                    wire:click="sincronizarTodasUnidades" 
                                    color="info" 
                                    icon="heroicon-m-cloud-arrow-down">
                                    Sincronizar Todas
                                </x-filament::button>
                            @endif

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
                                            <th class="px-4 py-3">Proprietário</th>
                                            <th class="px-4 py-3 text-right"><span class="sr-only">Ações</span></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($unidades as $u)
                                            <tr wire:click="replaceMountedAction('editarUnidadeAction', { unidadeId: {{ $u->id }} })" 
                                                class="cursor-pointer bg-white dark:bg-gray-900 hover:bg-primary-50 dark:hover:bg-gray-800 transition-colors group">
                                                
                                                <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">{{ $u->inscricao_imobiliaria ?? 'N/A' }}</td>
                                                <td class="px-4 py-3">{{ $u->codigo_imovel_tributario ?? 'N/A' }}</td>
                                                
                                                <td class="px-4 py-3">
                                                    @php
                                                        $nomeProprietario = 'Não informado';
                                                        if (!empty($u->dados_tributarios) && isset($u->dados_tributarios['proprietario_name'])) {
                                                            $nomeProprietario = $u->dados_tributarios['proprietario_name'];
                                                        } elseif ($u->proprietario_id) {
                                                            $pessoa = \App\Models\Pessoa::find($u->proprietario_id);
                                                            $nomeProprietario = $pessoa ? $pessoa->name : 'ID: ' . $u->proprietario_id;
                                                        }
                                                    @endphp
                                                    {{ $nomeProprietario }}
                                                </td>

                                                <td class="px-4 py-3 text-right">
                                                    {{-- 🛑 NOVO: Imprimir BIC (Usa stopPropagation para não abrir a edição sem querer) --}}
                                                    <button onclick="event.stopPropagation(); capturarMapaEImprimirBic({{ $u->id }})" class="text-amber-500 hover:text-amber-700 mr-3" title="Imprimir BIC">
                                                        <x-heroicon-o-printer class="w-5 h-5 inline-block transition-colors" />
                                                    </button>                                               

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
     * Ação: Criar Nova Unidade Imobiliária (inserir o Helper)
     */
    public function criarUnidadeAction(): Action
    {
        return Action::make('criarUnidadeAction')
            ->modalHeading('Cadastrar Nova Unidade Imobiliária')
            ->modalSubmitActionLabel('Salvar Unidade')
            ->modalWidth('xl')
            ->form([
                TextInput::make('inscricao_imobiliaria')
                    ->label('Inscrição Imobiliária')
                    ->maxLength(255)
                    ->unique(table: 'unidade_imobiliarias', column: 'inscricao_imobiliaria', modifyRuleUsing: fn(\Illuminate\Validation\Rules\Unique $rule) => $rule->where('tenant_id', $this->tenantId))
                    ->validationMessages(['unique' => 'Esta Inscrição já está cadastrada.']),

                TextInput::make('codigo_imovel_tributario')
                    ->label('Código do Imóvel Tributário')
                    ->maxLength(255)
                    ->unique(table: 'unidade_imobiliarias', column: 'codigo_imovel_tributario', modifyRuleUsing: fn(\Illuminate\Validation\Rules\Unique $rule) => $rule->where('tenant_id', $this->tenantId))
                    ->validationMessages(['unique' => 'Este Código Tributário já está cadastrado.'])
                    ->suffixAction(
                        \Filament\Forms\Components\Actions\Action::make('sincronizar_api')
                            ->icon('heroicon-o-cloud-arrow-down')
                            ->color('success')
                            ->tooltip('Buscar na Prefeitura')
                            ->action(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set) {
                                $codigo = $get('codigo_imovel_tributario');
                                if (!$codigo) return;
                                try {
                                    // 🛑 Passando o tenantId pro serviço para o futuro
                                    $dados = app(\App\Services\ApiTools\IntegraPrefeituraService::class)->buscarImovelPorCodigo($codigo, $this->tenantId);
                                    if ($dados) {
                                        // 🛑 Usa o nosso Helper para extrair endereço e criar a pessoa
                                        $payload = $this->processarDadosSincronizacao($dados);

                                        // 🛑 Preenche os campos do formulário visualmente
                                        $set('inscricao_imobiliaria', $payload['inscricao_imobiliaria']);
                                        $set('logradouro_nome', $payload['logradouro_nome']);
                                        $set('numero_imovel', $payload['numero_imovel']);
                                        $set('proprietario_id', $payload['proprietario_id']);
                                        
                                        // Mantém a visualização do JSON bonitinha no Textarea
                                        $set('dados_tributarios', json_encode($payload['dados_tributarios'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                        
                                        Notification::make()->title('Sincronizado!')->success()->send();
                                    }
                                } catch (\Exception $e) {
                                    Notification::make()->title('Erro')->body($e->getMessage())->danger()->send();
                                }
                            })
                    ),

                Select::make('proprietario_id')
                    ->label('Proprietário Principal')
                    ->options(fn() => \App\Models\Pessoa::pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),

                \Filament\Forms\Components\Textarea::make('dados_tributarios')
                    ->label('Dados Fiscais Sincronizados (API Prefeitura)')
                    ->disabled()
                    ->dehydrated() // Envia pro banco
                    ->rows(10)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) {
                // 🛑 BUG 3 RESOLVIDO: Decodifica antes de salvar
                if (isset($data['dados_tributarios']) && is_string($data['dados_tributarios'])) {
                    $decoded = json_decode($data['dados_tributarios'], true);
                    if (json_last_error() === JSON_ERROR_NONE) $data['dados_tributarios'] = $decoded;
                }

                $lote = Lote::find($this->loteAtivoId);
                $data['tenant_id'] = $this->tenantId;
                $data['lote_id'] = $this->loteAtivoId;
                $data['code'] = (string) Str::uuid();

                $unidade = UnidadeImobiliaria::create($data);

                if ($unidade && $lote) {
                    DB::statement("UPDATE unidade_imobiliarias SET geo = (SELECT ST_PointOnSurface(geo) FROM lotes WHERE id = ?) WHERE id = ?", [$lote->id, $unidade->id]);
                }

                Notification::make()->title('Unidade criada com sucesso!')->success()->send();
                $this->replaceMountedAction('verUnidades');
            })
            ->modalCancelAction(false)
            ->extraModalFooterActions([
                Action::make('voltar')
                    ->label('Voltar')
                    ->color('gray')
                    ->action(fn() => $this->replaceMountedAction('verUnidades')),
            ]);
    }

    /* Ação de Editar unidade (inserir o Helper) */
    public function editarUnidadeAction(): Action
    {
        return Action::make('editarUnidadeAction')
            ->modalHeading('Editar Unidade Imobiliária')
            ->modalSubmitActionLabel('Salvar Alterações')
            ->modalWidth('xl')
            ->fillForm(function (array $arguments): array {
                if (!isset($arguments['unidadeId'])) return [];
                $unidade = UnidadeImobiliaria::find($arguments['unidadeId']);
                return $unidade ? $unidade->toArray() : [];
            })
            ->form(function (array $arguments) {
                return [
                    TextInput::make('inscricao_imobiliaria')
                        ->label('Inscrição Imobiliária')
                        ->maxLength(255)
                        ->unique(
                            table: 'unidade_imobiliarias',
                            column: 'inscricao_imobiliaria',
                            // 🛑 BUG 1 RESOLVIDO: Ignora o ID diretamente na query
                            modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule) use ($arguments) {
                                $rule->where('tenant_id', $this->tenantId);
                                if (isset($arguments['unidadeId'])) $rule->ignore($arguments['unidadeId']);
                                return $rule;
                            }
                        )
                        ->validationMessages(['unique' => 'Esta Inscrição Imobiliária já está cadastrada.']),

                    TextInput::make('codigo_imovel_tributario')
                        ->label('Código do Imóvel Tributário')
                        ->maxLength(255)
                        ->unique(
                            table: 'unidade_imobiliarias',
                            column: 'codigo_imovel_tributario',
                            // 🛑 BUG 1 RESOLVIDO
                            modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule) use ($arguments) {
                                $rule->where('tenant_id', $this->tenantId);
                                if (isset($arguments['unidadeId'])) $rule->ignore($arguments['unidadeId']);
                                return $rule;
                            }
                        )
                        ->validationMessages(['unique' => 'Este Código Tributário já está cadastrado.'])
                        ->suffixAction(
                            \Filament\Forms\Components\Actions\Action::make('sincronizar_api')
                                ->icon('heroicon-o-cloud-arrow-down')
                                ->color('success')
                                ->tooltip('Buscar na Prefeitura')
                                ->action(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set) {
                                    $codigo = $get('codigo_imovel_tributario');
                                    if (!$codigo) {
                                        Notification::make()->title('Aviso')->body('Digite o código antes de buscar.')->warning()->send();
                                        return;
                                    }
                                    try {
                                        // 🛑 Passando o tenantId pro serviço para o futuro
                                        $apiService = app(\App\Services\ApiTools\IntegraPrefeituraService::class);
                                        $dados = $apiService->buscarImovelPorCodigo($codigo, $this->tenantId);
                                        
                                        if ($dados) {
                                            // 🛑 Usa o nosso Helper
                                            $payload = $this->processarDadosSincronizacao($dados);

                                            // 🛑 Atualiza os campos na tela do usuário
                                            $set('inscricao_imobiliaria', $payload['inscricao_imobiliaria']);
                                            $set('logradouro_nome', $payload['logradouro_nome']);
                                            $set('numero_imovel', $payload['numero_imovel']);
                                            $set('proprietario_id', $payload['proprietario_id']);

                                            $set('dados_tributarios', json_encode($payload['dados_tributarios'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                            
                                            Notification::make()->title('Sincronizado!')->success()->send();
                                        } else {
                                            Notification::make()->title('Não Encontrado')->danger()->send();
                                        }
                                    } catch (\Exception $e) {
                                        Notification::make()->title('Erro')->body($e->getMessage())->danger()->send();
                                    }
                                })
                        ),

                    Select::make('proprietario_id')
                        ->label('Proprietário Principal')
                        ->options(fn() => \App\Models\Pessoa::pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),

                    \Filament\Forms\Components\Textarea::make('dados_tributarios')
                        ->label('Dados Fiscais Sincronizados (API Prefeitura)')
                        ->formatStateUsing(function ($state) {
                            if (is_string($state)) $state = json_decode($state, true);
                            return $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'Ainda não sincronizado.';
                        })
                        ->disabled()
                        ->dehydrated() // 🛑 BUG 3 RESOLVIDO (Parte 2): Força o campo disabled a ser enviado pro banco!
                        ->rows(10)
                        ->columnSpanFull(),
                ];
            })
            ->action(function (array $data, array $arguments) {
                $unidade = UnidadeImobiliaria::find($arguments['unidadeId']);
                if ($unidade) {
                    // 🛑 BUG 3 RESOLVIDO (Parte 3): Decodifica a string do Textarea de volta para Array antes de salvar
                    if (isset($data['dados_tributarios']) && is_string($data['dados_tributarios'])) {
                        $decoded = json_decode($data['dados_tributarios'], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $data['dados_tributarios'] = $decoded;
                        }
                    }

                    $unidade->update($data);
                    Notification::make()->title('Unidade atualizada!')->success()->send();
                }
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
     * método de sincronização de forma individual por unidade (não esta sendo usado)
     */
    public function sincronizarUnidade($id)
    {
        $unidade = UnidadeImobiliaria::find($id);

        if (!$unidade || !$unidade->codigo_imovel_tributario) {
            Notification::make()->title('Aviso')->body('A unidade precisa ter o Código Tributário preenchido para sincronizar.')->warning()->send();
            return;
        }

        $apiService = app(\App\Services\ApiTools\IntegraPrefeituraService::class);
        $dados = $apiService->buscarImovelPorCodigo($unidade->codigo_imovel_tributario);

        try {

            if ($dados) {

                $payload = $this->processarDadosSincronizacao($dados);
                $unidade->update($payload);

                //$unidade->update(['inscricao_imobiliaria' => $dados['inscricao_imobiliaria'], 'dados_tributarios' => $dados]);
                Notification::make()->title('Sincronizado!')->success()->send();
            } else {
                Notification::make()->title('Não Encontrado')->body('O código não foi localizado na prefeitura.')->danger()->send();
            }
        } catch (\Exception $e) {
            Notification::make()->title('Erro')->body($e->getMessage())->danger()->send();
        }

        $this->mountAction('verUnidades');
    }

    /** 
     * método de sincronização de todas as unidades na modal de unidades
     */
    public function sincronizarTodasUnidades()
    {
        $unidades = UnidadeImobiliaria::where('lote_id', $this->loteAtivoId)
            ->whereNotNull('codigo_imovel_tributario')
            ->get();

        $sucesso = 0;
        $erros = 0;
        $apiService = app(\App\Services\ApiTools\IntegraPrefeituraService::class);

        foreach ($unidades as $unidade) {
            try {
                $dados = $apiService->buscarImovelPorCodigo($unidade->codigo_imovel_tributario);
                if ($dados) {
                    // Usa o helper que mastiga os dados, extrai o endereço e cria o proprietário!
                    $payload = $this->processarDadosSincronizacao($dados);
                    $unidade->update($payload);
                    $sucesso++;
                } else {
                    $erros++;
                }
            } catch (\Exception $e) {
                $erros++;
                // Se der erro na primeira vez, ele joga na tela para a gente ver o que quebrou de verdade
                Notification::make()->title('Erro Oculto')->body($e->getMessage())->danger()->send();
            }
        }

        if ($sucesso > 0) {
            Notification::make()->title('Concluído')->body("{$sucesso} unidades sincronizadas, endereços extraídos e proprietários cadastrados.")->success()->send();
        } elseif ($erros > 0) {
            Notification::make()->title('Aviso')->body('Nenhuma unidade foi sincronizada. Verifique os erros.')->warning()->send();
        }

        $this->replaceMountedAction('verUnidades');
    }

    /**
     * HELPER: Processa o JSON da API, extrai o endereço e auto-cadastra a Pessoa
     */
    private function processarDadosSincronizacao(array $dados): array
    {
        $nomeProprietario = $dados['proprietario_name'] ?? null;
        $pessoaId = null;

        // AUTO-CADASTRO DE PESSOA (Adicionando o 'type' obrigatório no momento da criação)
        if ($nomeProprietario) {
            $pessoa = \App\Models\Pessoa::firstOrCreate(
                // 1º Array: O que ele vai pesquisar no banco
                ['name' => $nomeProprietario, 'tenant_id' => $this->tenantId],
                // 2º Array: O que ele vai preencher JUNTO com o 1º array SE precisar criar um novo
                ['type' => 'fisica']
            );
            $pessoaId = $pessoa->id;
        }

        // Junta o Tipo (Rua) com o Nome do Logradouro
        $logradouroNome = trim(($dados['tipo_logradouro'] ?? '') . ' ' . ($dados['logradouro'] ?? ''));

        return [
            'inscricao_imobiliaria' => $dados['inscricao_imobiliaria'] ?? null,
            'logradouro_nome' => $logradouroNome ?: null,
            'numero_imovel' => (string) ($dados['numero_logradouro'] ?? 'S/N'),
            'proprietario_id' => $pessoaId,
            'dados_tributarios' => $dados, // Mantém o JSON original salvo
        ];
    }

    /**
     * Ação Global: Recebe a Imagem do Mapa via JS e devolve o PDF da BIC
     */
    public function imprimirBic($unidadeId, $mapImageBase64)
    {
        $unidade = \App\Models\UnidadeImobiliaria::find($unidadeId);

        if (!$unidade) {
            \Filament\Notifications\Notification::make()->title('Erro')->body('Unidade não encontrada.')->danger()->send();
            return;
        }

        // Aviso amigável caso a unidade ainda não tenha sido sincronizada com a Prefeitura
        if (empty($unidade->dados_tributarios)) {
            \Filament\Notifications\Notification::make()->title('Aviso')->body('A unidade ainda não foi sincronizada. Os valores tributários podem sair zerados.')->warning()->send();
        }

        // Instancia o seu serviço existente e faz o download!
        $service = app(\App\Services\Gis\BicPdfService::class);
        return $service->generatePdf($unidadeId, $mapImageBase64);
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
                        ->action(fn() => $this->replaceMountedAction('consultarViabilidadeAction')),

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
                        ->action(function () { /* Vazio propositalmente */
                        })
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

    /**
     * Ação: Gerar Memorial Descritivo em PDF
     */
    public function gerarMemorialAction(): Action
    {
        return Action::make('gerarMemorialAction')
            ->requiresConfirmation()
            ->modalHeading('Gerar Memorial Descritivo')
            ->modalDescription('Deseja gerar o documento legal com a descrição do perímetro e confrontantes para este lote?')
            ->modalSubmitActionLabel('Gerar PDF')
            ->icon('heroicon-o-document-text')
            ->color('success')
            ->action(function () {
                $lote = \App\Models\Lote::find($this->loteAtivoId);
                if (!$lote) {
                    \Filament\Notifications\Notification::make()->danger()->title('Erro')->body('Lote não encontrado.')->send();
                    return;
                }

                // 1. Instancia o nosso Service
                $service = app(\App\Services\Gis\MemorialDescritivoService::class);

                // 2. Coleta os dados matemáticos e o texto
                $textoMemorial = $service->gerarTextoMemorial($lote->id);
                $segmentos = $service->gerarDadosPerimetro($lote->id);

                // 3. Monta o PDF usando o DomPDF
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.memorial_descritivo', [
                    'lote' => $lote,
                    'textoMemorial' => $textoMemorial,
                    'segmentos' => $segmentos,
                    'dataExtenso' => now()->translatedFormat('d \d\e F \d\e Y'),
                    'tenantNome' => \Filament\Facades\Filament::getTenant()->name ?? 'Município',
                ]);

                // 4. Devolve o arquivo para download sem recarregar a página
                return response()->streamDownload(function () use ($pdf) {
                    echo $pdf->output();
                }, 'memorial_lote_' . ($lote->numero_lote ?? $lote->id) . '.pdf');
            });
    }

    /**
     * Ação Global: Recebe a Imagem do Mapa via JS e devolve o PDF do Croqui
     */
    public function imprimirCroqui($loteId, $mapImageBase64)
    {
        // Carrega o lote e as relações necessárias para o PDF
        $lote = \App\Models\Lote::with(['quadra.bairro', 'zona'])->find($loteId);

        if (!$lote) {
            \Filament\Notifications\Notification::make()->title('Erro')->body('Lote não encontrado.')->danger()->send();
            return;
        }

        $service = app(\App\Services\Gis\CroquiPdfService::class);
        return $service->generatePdf($lote, $mapImageBase64);
    }

    /**
     * Ação: Abrir Modal do Google Street View
     */
    public function abrirStreetViewAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('abrirStreetViewAction')
            ->modalHeading(fn() => 'Google Street View - Frente do Lote')
            ->modalWidth('5xl') // Modal bem larga para imersão
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(function () {
                // Busca o centroide exato do Lote usando PostGIS
                $lote = \App\Models\Lote::find($this->loteAtivoId);
                $centro = \Illuminate\Support\Facades\DB::selectOne("
                    SELECT ST_Y(ST_Centroid(geo::geometry)) as lat, ST_X(ST_Centroid(geo::geometry)) as lng 
                    FROM lotes WHERE id = ?
                ", [$lote->id]);

                $lat = $centro->lat ?? 0;
                $lng = $centro->lng ?? 0;

                // Renderiza a view com o Alpine.js gerenciando o mapa de forma segura
                $bladeView = <<<'BLADE'
                    <div x-data="{
                            init() {
                                // Delay para aguardar a animação da modal abrir
                                setTimeout(() => this.loadStreetView(), 200);
                            },
                            loadStreetView() {
                                const panoDiv = document.getElementById('street-view-modal-pano');
                                if (!panoDiv || typeof google === 'undefined' || !google.maps) return;

                                const svService = new google.maps.StreetViewService();
                                const centroDoLote = new google.maps.LatLng({{ $lat }}, {{ $lng }});

                                svService.getPanorama({ location: centroDoLote, radius: 50 }, (data, status) => {
                                    if (status === 'OK') {
                                        panoDiv.style.opacity = '1'; 
                                        
                                        const panorama = new google.maps.StreetViewPanorama(panoDiv, {
                                            position: data.location.latLng,
                                            zoom: 0,
                                            panControl: true,
                                            zoomControl: true,
                                            linksControl: true,
                                            clickToGo: true
                                        });
                                        
                                        // Vira a câmera exatamente para a porta do terreno!
                                        const anguloParaOLote = google.maps.geometry.spherical.computeHeading(data.location.latLng, centroDoLote);
                                        panorama.setPov({ heading: anguloParaOLote, pitch: 0 }); 
                                    }
                                });
                            }
                        }" 
                        wire:ignore style="height: 500px; width: 100%; position: relative; border-radius: 0.75rem; overflow: hidden; border: 1px solid #e5e7eb; background-color: #f3f4f6;" class="dark:border-gray-600 dark:bg-gray-800">
                        
                        <div id="street-view-modal-pano" style="position: absolute; inset: 0; width: 100%; height: 100%; z-index: 10; opacity: 0; transition: opacity 0.5s;"></div>
                        
                        <div id="street-view-error" style="position: absolute; inset: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 0;">
                            <x-heroicon-o-video-camera-slash style="width: 3rem; height: 3rem; opacity: 0.3; margin-bottom: 0.5rem; color: #6b7280;" />
                            <span style="font-size: 14px; font-weight: bold; text-transform: uppercase; color: #6b7280; opacity: 0.6;">Sem Cobertura do Street View</span>
                        </div>
                    </div>
                BLADE;

                return new \Illuminate\Support\HtmlString(\Illuminate\Support\Facades\Blade::render($bladeView, ['lat' => $lat, 'lng' => $lng]));
            });
    }
}
