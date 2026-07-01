<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrdemServicoResource\Pages;
use App\Models\OrdemServico;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class OrdemServicoResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'manutencao';

    protected static ?string $model = OrdemServico::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = 'Manutenção e Serviços';
    protected static ?string $modelLabel = 'Ordem de Serviço (OS)';
    protected static ?string $pluralModelLabel = 'Ordens de Serviço (OS)';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([

                    Forms\Components\Section::make('Vínculos e Localização')
                        ->schema([
                            Forms\Components\Select::make('solicitacao_id')
                                ->label('Chamado / Solicitação de Origem')
                                ->relationship('solicitacao', 'sequential_id')
                                ->getOptionLabelFromRecordUsing(fn($record) => "Chamado #{$record->sequential_id} - " . ucfirst($record->tipo_servico))
                                ->searchable()
                                ->preload()
                                ->placeholder('Selecione se houver (Opcional)')
                                ->live() // 🟢 NOVO: Ouve as mudanças
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    // 🟢 MÁGICA: Preenche tudo se você escolher na mão!
                                    if ($state) {
                                        $sol = \App\Models\SolicitacaoManutencao::find($state);
                                        if ($sol) {
                                            $set('asset_type', $sol->asset_type);
                                            $set('asset_id', $sol->asset_id);
                                            $set('prioridade', $sol->prioridade);
                                            $set('descricao_servico', "Ref. SM #{$sol->sequential_id}: " . $sol->observacao);
                                        }
                                    }
                                }),


                            // O MESMO MOTOR DE BUSCA AVANÇADO QUE FIZEMOS ANTES
                            Forms\Components\MorphToSelect::make('asset')
                                ->label('Artefato no Mapa (Obrigatório)')
                                ->types([
                                    Forms\Components\MorphToSelect\Type::make(\App\Models\Poste::class)
                                        ->titleAttribute('sequential_id')
                                        ->label('💡 Poste de Iluminação')
                                        ->getOptionLabelFromRecordUsing(fn($record) => "Poste #{$record->sequential_id} - Ref: " . ($record->address ?? 'S/N'))
                                        ->getSearchResultsUsing(function (string $search) {
                                            $tenantId = \Filament\Facades\Filament::getTenant()->id;
                                            return \App\Models\Poste::where('tenant_id', $tenantId)
                                                ->where(function ($query) use ($search) {
                                                    if (is_numeric($search))
                                                        $query->where('sequential_id', $search);
                                                    $query->orWhere('address', 'ilike', "%{$search}%");
                                                })->limit(50)->get()
                                                ->mapWithKeys(fn($poste) => [$poste->id => "Poste #{$poste->sequential_id} - Ref: " . ($poste->address ?? 'S/N')])->toArray();
                                        }),

                                    Forms\Components\MorphToSelect\Type::make(\App\Models\Arvore::class)
                                        ->titleAttribute('sequential_id')
                                        ->label('🌳 Árvore / Indivíduo Arbóreo')
                                        ->getOptionLabelFromRecordUsing(fn($record) => "Árvore #{$record->sequential_id} - Ref: " . ($record->address ?? 'S/N'))
                                        ->getSearchResultsUsing(function (string $search) {
                                            $tenantId = \Filament\Facades\Filament::getTenant()->id;
                                            return \App\Models\Arvore::where('tenant_id', $tenantId)
                                                ->where(function ($query) use ($search) {
                                                    if (is_numeric($search))
                                                        $query->where('sequential_id', $search);
                                                    $query->orWhere('address', 'ilike', "%{$search}%");
                                                })->limit(50)->get()
                                                ->mapWithKeys(fn($arvore) => [$arvore->id => "Árvore #{$arvore->sequential_id} - Ref: " . ($arvore->address ?? 'S/N')])->toArray();
                                        }),
                                ])
                                ->searchable()
                                ->required()
                                ->columnSpanFull(),
                        ]),

                    Forms\Components\Section::make('Detalhes da Execução')
                        ->schema([
                            Forms\Components\Textarea::make('descricao_servico')
                                ->label('Descrição do Serviço (O que deve ser feito?)')
                                ->rows(3)
                                ->required(),

                            Forms\Components\Textarea::make('laudo_tecnico')
                                ->label('Laudo Técnico (Preenchido no final do serviço)')
                                ->rows(3),
                        ]),

                    Forms\Components\Section::make('Materiais Utilizados')
                        ->description('Informe os produtos gastos nesta OS. O saldo será baixado automaticamente na conclusão.')
                        ->schema([

                            Forms\Components\Repeater::make('materiais')
                                ->relationship('materiais') // Salva na tabela os_materiais
                                ->defaultItems(0)
                                ->schema([
                                    Forms\Components\Select::make('produto_id')
                                        ->label('Produto / Material')
                                        ->relationship('produto', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->columnSpan(2),

                                    Forms\Components\Select::make('local_estoque_id')
                                        ->label('Retirado de (Local)')
                                        ->relationship('localEstoque', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('quantidade')
                                        ->label('Qtd')
                                        ->numeric()
                                        ->columnSpan(1)
                                        // 🟢 TRAVA DE ESTOQUE NEGATIVO 🟢
                                        ->rules([
                                            fn(Forms\Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                                $produtoId = $get('produto_id');
                                                $localId = $get('local_estoque_id');

                                                if ($produtoId && $localId) {
                                                    $saldoAtual = \App\Models\Estoque::where('local_estoque_id', $localId)
                                                        ->where('produto_id', $produtoId)
                                                        ->value('quantity') ?? 0;

                                                    if ($value > $saldoAtual) {
                                                        $fail("Saldo insuficiente! Você só tem {$saldoAtual} disp. neste local.");
                                                    }
                                                }
                                            },
                                        ]),
                                ])
                                ->columns(5)
                                ->addActionLabel('Adicionar Material'),
                        ]),
                ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Status e Prazos')
                        ->schema([
                            Forms\Components\Select::make('status')
                                ->label('Status da OS')
                                ->options([
                                    'aberta' => 'Aberta',
                                    'andamento' => 'Em Andamento',
                                    'pausada' => 'Pausada',
                                    'concluida' => 'Concluída',
                                    'cancelada' => 'Cancelada',
                                ])
                                ->default('aberta')
                                ->required(),

                            Forms\Components\Select::make('prioridade')
                                ->label('Prioridade')
                                ->options([
                                    'baixa' => '🟢 Baixa',
                                    'media' => '🟡 Média',
                                    'alta' => '🔴 Alta',
                                    'critica' => '⚫ Crítica',
                                ])
                                ->default('media')
                                ->required(),

                            Forms\Components\DatePicker::make('data_prevista')
                                ->label('Previsão de Execução'),

                            Forms\Components\DateTimePicker::make('data_inicio')
                                ->label('Data/Hora de Início'),

                            Forms\Components\DateTimePicker::make('data_fim')
                                ->label('Data/Hora de Conclusão'),
                        ]),

                    Forms\Components\Section::make('Equipe Designada')
                        ->schema([
                            // 🛑 O LINK COM O MÓDULO ADMINISTRATIVO (Pessoas)
                            Forms\Components\Select::make('equipe')
                                ->label('Selecionar Técnicos/Operacionais')
                                ->relationship('equipe', 'name')
                                ->multiple() // Permite selecionar várias pessoas!
                                ->searchable()
                                ->preload()
                                ->getOptionLabelFromRecordUsing(fn($record) => "{$record->name} - CPF: {$record->cpf_cnpj}"),
                        ]),

                    Forms\Components\Section::make('Evidências Fotográficas')
                        ->schema([
                            Forms\Components\FileUpload::make('foto_antes')
                                ->label('Foto - Antes do Serviço')
                                ->image()
                                ->directory('os_fotos'),

                            Forms\Components\FileUpload::make('foto_depois')
                                ->label('Foto - Serviço Concluído')
                                ->image()
                                ->directory('os_fotos'),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('OS #')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('asset_type')
                    ->label('Artefato')
                    ->formatStateUsing(function (string $state, $record) {
                        $tipo = class_basename($state);
                        return $tipo === 'Poste' ? "💡 Poste #{$record->asset->sequential_id}" : "🌳 Árvore #{$record->asset->sequential_id}";
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'aberta' => 'danger',
                        'andamento' => 'warning',
                        'pausada' => 'gray',
                        'concluida' => 'success',
                        'cancelada' => 'danger',
                    })
                    ->formatStateUsing(fn(string $state): string => strtoupper($state)),

                // Mostra o nome da primeira pessoa da equipe + contador se houver mais
                Tables\Columns\TextColumn::make('equipe.name')
                    ->label('Equipe')
                    ->badge()
                    ->limitList(1),

                Tables\Columns\TextColumn::make('data_prevista')
                    ->label('Previsto para')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'aberta' => 'Aberta',
                        'andamento' => 'Em Andamento',
                        'concluida' => 'Concluída',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('ver_no_mapa')
                        ->label('Ver no Mapa')
                        ->icon('heroicon-o-map-pin')
                        ->color('success')
                        ->visible(fn ($record) => $record->asset !== null)
                        ->url(function ($record) {
                            if (!$record->asset) return null;
                            $tenant = \Filament\Facades\Filament::getTenant();
                            $layer = str_contains($record->asset_type, 'Poste') ? 'postes' : 'arvores';
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=' . $layer . '&id=' . $record->asset_id);
                        })
                        ->openUrlInNewTab(),

                    Tables\Actions\DeleteAction::make(),

                    // 🛑 O BOTÃO DE IMPRIMIR OS APONTANDO PRA VIEW CERTA
                    Tables\Actions\Action::make('imprimir')
                        ->label('Imprimir Via (PDF)')
                        ->icon('heroicon-o-printer')
                        ->color('info')
                        ->action(function (\App\Models\OrdemServico $record) {
                            // Tenta gerar mini-mapa a partir das coordenadas do asset
                            $mapImageBase64 = null;
                            if ($record->asset_id && $record->asset_type) {
                                try {
                                    $table  = str_contains($record->asset_type, 'Poste') ? 'postes' : 'arvores';
                                    $coords = \Illuminate\Support\Facades\DB::selectOne(
                                        "SELECT ST_X(geo::geometry) AS lon, ST_Y(geo::geometry) AS lat FROM {$table} WHERE id = ?",
                                        [$record->asset_id]
                                    );
                                    if ($coords && $coords->lat && $coords->lon) {
                                        $mapImageBase64 = \App\Services\Gis\StaticMapService::generate(
                                            (float) $coords->lat,
                                            (float) $coords->lon,
                                            17
                                        );
                                    }
                                } catch (\Throwable $e) {
                                    // Coordenadas indisponíveis — PDF sai sem mapa
                                }
                            }

                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.ordem-servico-pdf-template', [
                                'title'          => 'Ordem de Serviço',
                                'ordemServico'   => $record,
                                'mapImageBase64' => $mapImageBase64,
                            ]);

                            return response()->streamDownload(fn() => print($pdf->stream()), "OS-{$record->sequential_id}.pdf");
                        }),

                ])->tooltip('Ações'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrdemServicos::route('/'),
            'create' => Pages\CreateOrdemServico::route('/create'),
            'edit' => Pages\EditOrdemServico::route('/{record}/edit'),
        ];
    }
}