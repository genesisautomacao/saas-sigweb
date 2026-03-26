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
    protected static ?int $navigationSort = 1;

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

                        Forms\Components\Textarea::make('descricao')
                            ->label('Descrição do Fluxo')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Editor Visual BPMN')
                    ->description('Desenhe o fluxo arrastando os elementos. Cada tarefa (caixa) representará uma etapa do processo digital.')
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

                Tables\Columns\TextColumn::make('nome')
                    ->label('Nome do Fluxo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\IconColumn::make('ativo')
                    ->boolean()
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