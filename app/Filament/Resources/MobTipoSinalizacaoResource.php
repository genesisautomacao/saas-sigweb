<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MobTipoSinalizacaoResource\Pages;
use App\Models\MobTipoSinalizacao;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Catálogo de tipos de sinalização (Mobilidade Urbana — decisão 6.1 do
 * piuma.txt): nome + vertical/horizontal + cor + ícone + código CTB.
 * Tipo em uso não pode ser excluído (FK restrict) — desative em vez disso.
 */
class MobTipoSinalizacaoResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'mob_infra';

    protected static ?string $model = MobTipoSinalizacao::class;

    protected static ?string $tenantRelationshipName = 'mobTiposSinalizacao';

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationGroup = 'Mobilidade Urbana';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Tipo de Sinalização';

    protected static ?string $pluralModelLabel = 'Catálogo de Sinalização';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(150),
            Forms\Components\Select::make('tipo')
                ->label('Tipo')
                ->options(MobTipoSinalizacao::TIPOS)
                ->required(),
            Forms\Components\TextInput::make('codigo_ctb')
                ->label('Código CTB')
                ->placeholder('R-1, A-12...')
                ->maxLength(20),
            Forms\Components\ColorPicker::make('cor')
                ->label('Cor no mapa'),
            Forms\Components\FileUpload::make('icone')
                ->label('Ícone (opcional)')
                ->helperText('PNG/JPG pequeno — substitui o círculo/losango no mapa.')
                ->directory('mob_sinalizacao/icones')
                ->image()
                ->maxSize(1024),
            Forms\Components\Toggle::make('ativo')
                ->label('Ativo')
                ->helperText('Tipo aposentado some das listas, mas as placas já cadastradas continuam.')
                ->default(true)
                ->inline(false),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('cor')->label('Cor'),
                Tables\Columns\ImageColumn::make('icone')->label('Ícone')->circular()->toggleable(),
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => MobTipoSinalizacao::TIPOS[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'vertical' ? 'info' : 'warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('codigo_ctb')->label('CTB')->placeholder('—'),
                Tables\Columns\TextColumn::make('sinalizacoes_count')
                    ->label('Placas')
                    ->counts('sinalizacoes')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')->options(MobTipoSinalizacao::TIPOS),
                Tables\Filters\TernaryFilter::make('ativo')->label('Ativo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    // FK restrict: tipo com placas não apaga — orienta a desativar
                    ->before(function (Tables\Actions\DeleteAction $action, MobTipoSinalizacao $record) {
                        if ($record->sinalizacoes()->exists()) {
                            Notification::make()->danger()
                                ->title('Tipo em uso')
                                ->body('Existem placas cadastradas com este tipo. Reclassifique-as ou apenas DESATIVE o tipo.')
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->defaultSort('ordem');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListMobTiposSinalizacao::route('/')];
    }
}
