<?php

namespace App\Filament\Cidadao\Resources;

use App\Filament\Cidadao\Resources\ProcessoDigitalResource\Pages;
use App\Models\ProcessoDigital;
use App\Models\BpmnFluxo;
use App\Models\BpmnEtapa;
use App\Services\Processo\ProcessoFormService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProcessoDigitalResource extends Resource
{
    // No painel Cidadão, qualquer usuário autenticado pode ver e criar seus
    // próprios processos. A trava real está no modifyQueryUsing (requerente_id).
    public static function canViewAny(): bool { return Auth::check(); }
    public static function canCreate(): bool { return Auth::check(); }
    public static function canView($record): bool { return Auth::id() === $record->requerente_id; }
    public static function canEdit($record): bool { return Auth::id() === $record->requerente_id; }
    public static function canDelete($record): bool { return false; }
    protected static ?string $model = ProcessoDigital::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';
    protected static ?string $modelLabel = 'Meu Processo';
    protected static ?string $pluralModelLabel = 'Meus Processos';
    protected static ?string $navigationLabel = 'Meus Processos';

    /** Estados em que o cidadão pode editar o formulário (item 129/220 — B16). */
    public const STATUS_EDITAVEIS = ['rascunho', 'pendente_correcao', 'aguardando_solicitante'];

    /** A etapa cujo formulário deve ser renderizado: na abertura = 1ª; depois = etapa atual. */
    public static function etapaDoFormulario(?ProcessoDigital $record, $fluxoId): ?BpmnEtapa
    {
        if ($record && $record->etapa_atual_id) {
            return BpmnEtapa::find($record->etapa_atual_id);
        }

        if (!$fluxoId) {
            return null;
        }

        return BpmnEtapa::where('bpmn_fluxo_id', $fluxoId)->orderBy('ordem')->orderBy('id')->first();
    }

    /** O cidadão pode editar? (abertura sempre; edição só em estados de correção/aguardo). */
    public static function podeEditar(?ProcessoDigital $record): bool
    {
        return $record === null || in_array($record->status, self::STATUS_EDITAVEIS);
    }

    public static function form(Form $form): Form
    {
        // Criação = wizard (tipo → imóvel → dados). Edição/correção = apenas contexto + campos da
        // etapa atual — o cidadão NÃO reabre o wizard nem troca o tipo/lote depois de solicitado.
        // Usa $record (null = criação) num Group: injeção confiável em closure de componente
        // (o $operation NÃO é resolvível no closure de topo do Form).
        return $form->schema([
            Forms\Components\Group::make()
                ->columnSpanFull()
                ->schema(fn (?ProcessoDigital $record) => $record === null
                    ? self::schemaCriacao()
                    : self::schemaCorrecao()),
        ]);
    }

    /** Wizard de abertura do processo (somente na criação). */
    protected static function schemaCriacao(): array
    {
        return [
                Forms\Components\Wizard::make([
                    // PASSO 1: O QUE O CIDADÃO QUER? (bloqueado após a abertura)
                    Forms\Components\Wizard\Step::make('Tipo de Pedido')
                        ->icon('heroicon-o-document-magnifying-glass')
                        ->schema([
                            Forms\Components\Select::make('bpmn_fluxo_id')
                                ->label('Qual serviço deseja solicitar?')
                                // Item 4 — só os fluxos ativos da prefeitura (tenant) do cidadão logado
                                ->options(fn () => BpmnFluxo::query()
                                    ->where('ativo', true)
                                    ->when(
                                        Auth::user()?->tenants->first()?->id,
                                        fn ($q, $tid) => $q->where('tenant_id', $tid)
                                    )
                                    ->pluck('nome', 'id'))
                                ->required()
                                ->reactive()
                                ->disabled(fn (?ProcessoDigital $record) => filled($record)) // não muda o tipo depois de aberto
                                ->helperText('Ex: Aprovação de Projeto, REURB, Habite-se.'),
                        ]),

                    // PASSO 2: SELEÇÃO DO IMÓVEL — depende do modo_imovel do fluxo (item 2)
                    Forms\Components\Wizard\Step::make('Localização do Imóvel')
                        ->icon('heroicon-o-map')
                        ->visible(fn (Get $get, ?ProcessoDigital $record) => self::modoImovel($get, $record) !== 'nenhum')
                        ->schema(function (Get $get, ?ProcessoDigital $record) {
                            $modo = self::modoImovel($get, $record);
                            $disabled = !self::podeEditar($record);
                            $campos = [];

                            if ($modo === 'mapa') {
                                $campos[] = Forms\Components\Placeholder::make('dica_mapa')
                                    ->hiddenLabel()
                                    ->content(new \Illuminate\Support\HtmlString('
                                        <div class="p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50 dark:bg-gray-800 dark:text-blue-400" role="alert">
                                          <span class="font-medium">Instrução:</span> Navegue pelo mapa e clique em cima do seu Lote/Terreno.
                                        </div>
                                    '));

                                $campos[] = Forms\Components\ViewField::make('lote_id')
                                    ->hiddenLabel()
                                    ->view('filament.forms.components.mapa-selecao-lote')
                                    ->required()
                                    ->disabled($disabled)
                                    ->helperText('Obrigatório clicar num lote para avançar.');
                            } elseif ($modo === 'busca') {
                                $campos[] = Forms\Components\Select::make('lote_id')
                                    ->label('Buscar imóvel (nº do lote ou código tributário)')
                                    ->required()
                                    ->disabled($disabled)
                                    ->searchable()
                                    ->live()
                                    ->getSearchResultsUsing(fn (string $search) => self::buscarLotes($search))
                                    ->getOptionLabelUsing(fn ($value) => self::rotuloLotePorId($value))
                                    ->helperText('Digite o número do lote ou o código tributário e selecione.');
                            }

                            // Ficha do imóvel selecionado (itens 130/141/221) — em ambos os modos
                            $campos[] = Forms\Components\Placeholder::make('dados_imovel')
                                ->label('Dados do Imóvel Selecionado')
                                ->visible(fn (Get $get) => filled($get('lote_id')))
                                ->content(fn (Get $get) => new \Illuminate\Support\HtmlString(
                                    ProcessoFormService::dadosImovelHtml($get('lote_id'))
                                ));

                            return $campos;
                        }),

                    // PASSO 3: O FORMULÁRIO DA ETAPA ATUAL (abertura = 1ª etapa; retorno = etapa atual)
                    Forms\Components\Wizard\Step::make('Formulário / Documentação')
                        ->icon('heroicon-o-paper-clip')
                        ->schema(function (Get $get, ?ProcessoDigital $record) {
                            $etapa = self::etapaDoFormulario($record, $get('bpmn_fluxo_id'));
                            $disabled = !self::podeEditar($record);

                            $schema = [];

                            // Item 3 — os documentos agora são campos de upload NOMEADOS do formulário
                            // da etapa (ex.: "Foto do CPF", "Escritura"). Não há mais anexos soltos.
                            $campos = ProcessoFormService::camposDaEtapa($etapa, $disabled);
                            if (!empty($campos)) {
                                $schema[] = Forms\Components\Section::make('Preenchimento Exigido')
                                    ->description($etapa?->nome ? "Etapa: {$etapa->nome}" : 'Preencha os dados solicitados pela prefeitura.')
                                    ->schema($campos)
                                    ->columns(2);
                            } else {
                                $schema[] = Forms\Components\Placeholder::make('sem_campos')
                                    ->hiddenLabel()
                                    ->content('Esta etapa não exige preenchimento adicional. Clique em "Enviar para Análise".');
                            }

                            return $schema;
                        }),
                ])
                    // Item 6 — o botão de envio só aparece na ÚLTIMA etapa do wizard
                    ->submitAction(new \Illuminate\Support\HtmlString(
                        '<button type="submit" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-70">Enviar para Análise</button>'
                    ))
                    ->columnSpanFull(),
        ];
    }

    /** Tela de correção/resposta (edição): aviso + resumo + formulário da etapa atual (sem trocar tipo/lote). */
    protected static function schemaCorrecao(): array
    {
        return [
            Forms\Components\Placeholder::make('aviso_reprovacao')
                ->hiddenLabel()
                ->visible(fn (?ProcessoDigital $record) => $record && $record->status === 'pendente_correcao')
                ->content(function (?ProcessoDigital $record) {
                    $tramitacao = \App\Models\ProcessoTramitacao::where('processo_digital_id', $record->id)
                        ->where('status_parecer', 'reprovado')
                        ->latest()
                        ->first();

                    $motivo = $tramitacao ? $tramitacao->parecer : 'Por favor, verifique os dados e reenvie.';

                    return new \Illuminate\Support\HtmlString("
                        <div class='p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400 border-2 border-red-500' role='alert'>
                          <div class='flex items-center gap-2 font-bold text-lg mb-2'>
                            <svg class='w-6 h-6' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'></path></svg>
                            Processo Devolvido para Correção!
                          </div>
                          <p class='text-base font-medium'><strong>Motivo / Despacho do Analista:</strong> {$motivo}</p>
                        </div>
                    ");
                })
                ->columnSpanFull(),

            Forms\Components\Section::make('Resumo da Solicitação')
                ->collapsible()
                ->schema([
                    Forms\Components\Placeholder::make('resumo')
                        ->hiddenLabel()
                        ->content(fn (?ProcessoDigital $record) => new \Illuminate\Support\HtmlString(self::resumoHtml($record))),
                ]),

            Forms\Components\Section::make('Preenchimento da Etapa Atual')
                ->description(fn (?ProcessoDigital $record) => 'Etapa: ' . ($record?->etapaAtual?->nome ?? '—'))
                ->schema(function (?ProcessoDigital $record) {
                    $etapa = $record?->etapaAtual;
                    $disabled = !self::podeEditar($record);
                    $campos = ProcessoFormService::camposDaEtapa($etapa, $disabled);

                    return !empty($campos) ? $campos : [
                        Forms\Components\Placeholder::make('sem_campos')
                            ->hiddenLabel()
                            ->content('Esta etapa não exige preenchimento adicional. Clique em "Enviar para Análise".'),
                    ];
                })
                ->columns(2),
        ];
    }

    /** Resumo read-only do processo (protocolo, serviço, fase, imóvel) para a tela de correção. */
    protected static function resumoHtml(?ProcessoDigital $record): string
    {
        if (!$record) {
            return '';
        }

        $linha = fn ($rotulo, $valor) => '<div class="flex justify-between gap-4 py-0.5">'
            . '<span class="text-gray-500">' . e($rotulo) . '</span>'
            . '<span class="font-medium text-right">' . e($valor ?: '—') . '</span></div>';

        $html = '<div class="text-sm space-y-0.5">';
        $html .= $linha('Protocolo', $record->codigo_processo);
        $html .= $linha('Serviço', $record->fluxo?->nome);
        $html .= $linha('Fase atual', $record->etapaAtual?->nome);
        $html .= '</div>';

        if ($record->lote_id) {
            $html .= '<div class="mt-2">' . ProcessoFormService::dadosImovelHtml($record->lote_id) . '</div>';
        }

        return $html;
    }

    /** Resolve se o fluxo escolhido (ou o do processo) exige seleção de imóvel. */
    /** Modo de seleção de imóvel do fluxo escolhido/do processo (item 2): nenhum | mapa | busca. */
    protected static function modoImovel(Get $get, ?ProcessoDigital $record): string
    {
        if ($record && $record->fluxo) {
            return $record->fluxo->modo_imovel ?? 'mapa';
        }

        $fluxoId = $get('bpmn_fluxo_id');
        if (!$fluxoId) {
            return 'mapa';
        }

        return BpmnFluxo::whereKey($fluxoId)->value('modo_imovel') ?? 'mapa';
    }

    /** Busca lotes por número do lote OU código tributário (modo 'busca' — item 2). */
    protected static function buscarLotes(string $search): array
    {
        $tenantId = Auth::user()?->tenants->first()?->id;

        return \App\Models\Lote::query()
            ->withoutGlobalScopes()
            ->with('unidadesImobiliarias')
            ->when($tenantId, fn ($q) => $q->where('lotes.tenant_id', $tenantId))
            ->where(function ($q) use ($search) {
                $q->where('numero_lote', 'ilike', "%{$search}%")
                    ->orWhereHas('unidadesImobiliarias', fn ($u) => $u->where('codigo_imovel_tributario', 'ilike', "%{$search}%"));
            })
            ->limit(30)
            ->get()
            ->mapWithKeys(fn ($l) => [$l->id => self::rotuloLote($l)])
            ->toArray();
    }

    protected static function rotuloLote($lote): string
    {
        $cad = optional($lote->unidadesImobiliarias->first())->codigo_imovel_tributario;

        return 'Lote ' . ($lote->numero_lote ?? 'S/N') . ($cad ? " — Cód. {$cad}" : '');
    }

    protected static function rotuloLotePorId($value): ?string
    {
        if (!$value) {
            return null;
        }

        $lote = \App\Models\Lote::withoutGlobalScopes()->with('unidadesImobiliarias')->find($value);

        return $lote ? self::rotuloLote($lote) : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('requerente_id', Auth::id()))
            ->columns([
                Tables\Columns\TextColumn::make('codigo_processo')
                    ->label('Protocolo')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('fluxo.nome')
                    ->label('Serviço')
                    ->sortable(),

                Tables\Columns\TextColumn::make('etapaAtual.nome')
                    ->label('Fase Atual')
                    ->badge()
                    ->color('warning')
                    ->default('Aguardando Triagem'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'gray' => 'rascunho',
                        'primary' => 'em_andamento',
                        'info' => 'aguardando_solicitante',
                        'success' => 'concluido',
                        'danger' => ['pendente_correcao', 'cancelado'],
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aberto em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Acompanhar'),
                Tables\Actions\EditAction::make()
                    ->label(fn (ProcessoDigital $record) => match ($record->status) {
                        'pendente_correcao' => 'Corrigir Processo',
                        'aguardando_solicitante' => 'Responder Etapa',
                        default => 'Continuar Rascunho',
                    })
                    ->color(fn (ProcessoDigital $record) => $record->status === 'pendente_correcao' ? 'danger' : 'primary')
                    ->visible(fn (ProcessoDigital $record) => in_array($record->status, self::STATUS_EDITAVEIS)),
            ])
            ->emptyStateHeading('Nenhum processo em andamento')
            ->emptyStateDescription('Clique no botão acima para iniciar uma nova solicitação na prefeitura.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcessoDigitals::route('/'),
            'create' => Pages\CreateProcessoDigital::route('/create'),
            'view' => Pages\ViewProcessoDigital::route('/{record}'),
            'edit' => Pages\EditProcessoDigital::route('/{record}/edit'),
        ];
    }
}
