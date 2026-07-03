<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BpmnFluxoResource\Pages;
use App\Models\BpmnFluxo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class BpmnFluxoResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'processos'; // Um novo módulo!

    protected static ?string $model = BpmnFluxo::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $modelLabel = 'Fluxo de Processo (BPMN)';
    protected static ?string $pluralModelLabel = 'Fluxos BPMN';
    protected static ?string $navigationGroup = 'Processos Digitais';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação do Fluxo')
                    ->schema([
                        Forms\Components\TextInput::make('nome')
                            ->label('Nome do Processo (Ex: Aprovação REURB)')
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\Toggle::make('ativo')
                            ->label('Fluxo Ativo')
                            ->default(true),

                        // Cor deste fluxo no mapa (camada "Processos Digitais")
                        Forms\Components\ColorPicker::make('cor')
                            ->label('Cor no mapa')
                            ->default('#3b82f6')
                            ->helperText('Cor com que os lotes deste fluxo são marcados na camada de Processos do mapa.'),

                        // Decisão #5 / item 2: modo de seleção de imóvel (processosConceito.md §9.5)
                        Forms\Components\Select::make('modo_imovel')
                            ->label('Seleção de imóvel')
                            ->options([
                                'nenhum' => 'Sem necessidade de imóvel',
                                'mapa'   => 'Mostrar mapa para localização do imóvel',
                                'busca'  => 'Seleção de imóvel por busca (nº do lote / código tributário)',
                            ])
                            ->default('mapa')
                            ->required()
                            ->native(false)
                            ->helperText('Define se e como o cidadão informa o imóvel ao abrir o processo (itens 130/221).'),

                        Forms\Components\Textarea::make('descricao')
                            ->label('Descrição do Fluxo')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Editor Visual BPMN')
                    ->description('Desenhe o fluxo arrastando os elementos. Cada tarefa (caixa) representará uma etapa do processo digital.')
                    // Item 3 — recolhido por padrão para não atrapalhar a rolagem; abre ao clicar.
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        // O NOSSO COMPONENTE CUSTOMIZADO SENDO CHAMADO AQUI:
                        Forms\Components\ViewField::make('xml_diagrama')
                            ->hiddenLabel()
                            ->view('filament.forms.components.bpmn-modeler')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\BpmnFluxoResource\RelationManagers\EtapasRelationManager::class,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\ColorColumn::make('cor')
                    ->label('Cor'),

                Tables\Columns\TextColumn::make('nome')
                    ->label('Nome do Fluxo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\ToggleColumn::make('ativo')
                    ->label('Ativo')
                    ->onColor('success')
                    ->offColor('danger')
                    ->tooltip('Ativa/desativa o fluxo. Fluxos inativos não aparecem na abertura de novos processos.')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Alteração')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBpmnFluxos::route('/'),
            'create' => Pages\CreateBpmnFluxo::route('/create'),
            'edit' => Pages\EditBpmnFluxo::route('/{record}/edit'),
        ];
    }
}