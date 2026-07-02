<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaceQuadraResource\Pages;
use App\Models\FaceQuadra;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FaceQuadraResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'pgv';
    protected static ?string $tenantRelationshipName = 'faceQuadras';

    protected static ?string $model = FaceQuadra::class;
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    protected static ?string $navigationGroup = 'Gestão Tributária (PGV)';
    protected static ?string $modelLabel = 'Face de Quadra';
    protected static ?string $pluralModelLabel = 'Faces de Quadra';
    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->label('Código da Seção/Face')->maxLength(255),
            Forms\Components\TextInput::make('name')->label('Nome')->maxLength(255),
            Forms\Components\Select::make('quadra_id')->label('Quadra')
                ->relationship('quadra', 'name')->searchable()->required(),
            Forms\Components\Select::make('logradouro_id')->label('Logradouro (confrontante)')
                ->relationship('logradouro', 'name')->searchable(),
            Forms\Components\Select::make('zona_id')->label('Zona')
                ->relationship('zona', 'name')->searchable(),
            Forms\Components\Placeholder::make('geo_info')->label('Geometria')
                ->content('A linha da face é desenhada pela ficha da Quadra no mapa (botão "Ver Faces").')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Código')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('quadra.name')->label('Quadra')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('logradouro.name')->label('Logradouro')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('zona.sigla')->label('Zona')->badge()->placeholder('—'),
                Tables\Columns\TextColumn::make('extensao_geo')->label('Extensão')->numeric(2)->suffix(' m')->placeholder('—'),
                Tables\Columns\TextColumn::make('valor_m2_calculado')->label('Valor m² (PGV)')->money('BRL')
                    ->color('success')->weight('bold')->placeholder('não calculado'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('quadra_id')->label('Quadra')->relationship('quadra', 'name')->searchable()->preload(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFaceQuadras::route('/')];
    }
}
