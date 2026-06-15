<?php

namespace App\Filament\Cidadao\Resources;

use App\Filament\Cidadao\Resources\ProcessoDigitalResource\Pages;
use App\Models\ProcessoDigital;
use App\Models\BpmnFluxo;
use Filament\Forms;
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('aviso_reprovacao')
                    ->hiddenLabel()
                    ->visible(fn(?ProcessoDigital $record) => $record && $record->status === 'pendente_correcao')
                    ->content(function (?ProcessoDigital $record) {
                        // Busca o motivo exato que o fiscal digitou
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

                Forms\Components\Wizard::make([
                    // PASSO 1: O QUE O CIDADÃO QUER?
                    Forms\Components\Wizard\Step::make('Tipo de Pedido')
                        ->icon('heroicon-o-document-magnifying-glass')
                        ->schema([
                            Forms\Components\Select::make('bpmn_fluxo_id')
                                ->label('Qual serviço deseja solicitar?')
                                ->options(BpmnFluxo::where('ativo', true)->pluck('nome', 'id'))
                                ->required()
                                ->reactive() // Atualiza o ecrã dinamicamente
                                ->helperText('Ex: Aprovação de Projeto, REURB, Habite-se.'),
                        ]),

                    // PASSO 2: A MÁGICA DO MAPA (Exigência da PoC)
                    Forms\Components\Wizard\Step::make('Localização (Mapa)')
                        ->icon('heroicon-o-map')
                        ->schema([
                            Forms\Components\Placeholder::make('dica_mapa')
                                ->hiddenLabel()
                                ->content(new \Illuminate\Support\HtmlString('
                                    <div class="p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50 dark:bg-gray-800 dark:text-blue-400" role="alert">
                                      <span class="font-medium">Instrução:</span> Navegue pelo mapa abaixo e clique em cima do seu Lote/Terreno. O sistema irá selecioná-lo para este processo.
                                    </div>
                                ')),

                            // AQUI ESTÁ O NOSSO COMPONENTE CUSTOMIZADO SENDO CHAMADO!
                            Forms\Components\ViewField::make('lote_id')
                                ->hiddenLabel()
                                ->view('filament.forms.components.mapa-selecao-lote')
                                ->required()
                                ->helperText('Obrigatório clicar num lote para avançar.'),
                        ]),

                    // PASSO 3: O PREENCHIMENTO DO FORMULÁRIO DINÂMICO
                    Forms\Components\Wizard\Step::make('Documentação')
                        ->icon('heroicon-o-paper-clip')
                        ->schema(function (\Filament\Forms\Get $get) {
                            $fluxoId = $get('bpmn_fluxo_id');
                            $schemaDinamico = [];

                            // 1. GERA OS CAMPOS DINÂMICOS BASEADOS NA ETAPA 1 DO FLUXO ESCOLHIDO
                            if ($fluxoId) {
                                $primeiraEtapa = \App\Models\BpmnEtapa::where('bpmn_fluxo_id', $fluxoId)
                                    ->orderBy('id', 'asc')
                                    ->first();

                                if ($primeiraEtapa && !empty($primeiraEtapa->campos_formulario)) {
                                    $camposFilament = collect($primeiraEtapa->campos_formulario)->map(function ($campo) {
                                        $tipo = $campo['type'];
                                        $dados = $campo['data'];
                                        $nomeCampo = 'dados_formulario.' . \Illuminate\Support\Str::slug($dados['label_campo']);

                                        if ($tipo === 'texto' || $tipo === 'mapa') {
                                            return Forms\Components\TextInput::make($nomeCampo)
                                                ->label($dados['label_campo'])
                                                ->required($dados['obrigatorio'] ?? false);
                                        }
                                        if ($tipo === 'checkbox') {
                                            return Forms\Components\CheckboxList::make($nomeCampo)
                                                ->label($dados['label_campo'])
                                                ->options(array_combine($dados['opcoes'], $dados['opcoes']))
                                                ->required($dados['obrigatorio'] ?? false)
                                                ->default([])
                                                // 🛑 A VACINA DO CHECKBOX: Força o Livewire a entender que é um Array desde o início
                                                ->formatStateUsing(fn($state) => $state === null ? [] : (is_array($state) ? $state : [$state]));
                                        }
                                        if ($tipo === 'documento') {
                                            $input = Forms\Components\TextInput::make($nomeCampo)
                                                ->label($dados['label_campo'])
                                                ->required($dados['obrigatorio'] ?? false);

                                            // Aplica a máscara correta
                                            if (($dados['mascara'] ?? '') === 'cpf') {
                                                $input->mask('999.999.999-99');
                                            } else {
                                                $input->mask('(99) 99999-9999');
                                            }
                                            return $input;
                                        }
                                    })->filter()->toArray();

                                    $schemaDinamico[] = Forms\Components\Section::make('Preenchimento Exigido')
                                        ->description('Preencha os dados solicitados pela prefeitura para este tipo de processo.')
                                        ->schema($camposFilament)->columns(2);
                                }
                            }

                            // 2. O CAMPO PADRÃO DE ANEXOS
                            $schemaDinamico[] = Forms\Components\Section::make('Anexos')
                                ->schema([
                                    Forms\Components\FileUpload::make('anexos_temporarios')
                                        ->label('Anexe os documentos exigidos (PDF, JPG, PNG)')
                                        ->multiple()
                                        ->directory('processos_anexos')
                                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                        ->maxSize(5120) // 5MB por arquivo
                                        // 🛑 CORREÇÃO 2: Ensina o Filament a buscar os anexos na tabela correta ao visualizar/editar
                                        ->afterStateHydrated(function (Forms\Components\FileUpload $component, ?\App\Models\ProcessoDigital $record) {
                                            if ($record && $record->exists) {
                                                $anexos = \App\Models\ProcessoAnexo::where('processo_digital_id', $record->id)
                                                    ->pluck('caminho_arquivo')
                                                    ->toArray();
                                                $component->state($anexos);
                                            }
                                        })
                                ]);

                            return $schemaDinamico;
                        }),
                ])
                    ->columnSpanFull()
                // 🛑 CORREÇÃO 3 (Parte A): A linha do submitAction foi apagada daqui para não duplicar o botão!
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // 🛑 A TRAVA DE SEGURANÇA: O Cidadão só vê o que é dele!
            ->modifyQueryUsing(fn(Builder $query) => $query->where('requerente_id', Auth::id()))
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
                        'success' => 'concluido',
                        'danger' => 'pendente_correcao', // 🛑 ADICIONADO AQUI
                        'danger' => 'cancelado',
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
                    ->label(fn (ProcessoDigital $record) => $record->status === 'pendente_correcao' ? 'Corrigir Processo' : 'Continuar Rascunho')
                    ->color(fn (ProcessoDigital $record) => $record->status === 'pendente_correcao' ? 'danger' : 'primary')
                    ->visible(fn (ProcessoDigital $record) => in_array($record->status, ['rascunho', 'pendente_correcao'])),
            ])
            ->emptyStateHeading('Nenhum processo em andamento')
            ->emptyStateDescription('Clique no botão acima para iniciar uma nova solicitação na prefeitura.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcessoDigitals::route('/'),
            'create' => Pages\CreateProcessoDigital::route('/create'),
            'edit' => Pages\EditProcessoDigital::route('/{record}/edit'),
        ];
    }
}
