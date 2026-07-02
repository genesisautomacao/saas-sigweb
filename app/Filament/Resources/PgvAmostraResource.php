<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PgvAmostraResource\Pages;
use App\Models\PgvAmostra;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PgvAmostraResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'pgv';
    protected static ?string $tenantRelationshipName = 'pgvAmostras';

    protected static ?string $model = PgvAmostra::class;
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Gestão Tributária (PGV)';
    protected static ?string $modelLabel = 'Amostra de Mercado';
    protected static ?string $pluralModelLabel = 'Amostras de Mercado';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Valor e Atributos (para homogeneização)')
                ->description('O ponto da amostra é definido clicando no mapa (Ferramentas → Nova Amostra).')
                ->schema([
                    Forms\Components\TextInput::make('valor_m2')->label('Valor m² observado (mercado)')->numeric()->prefix('R$')->required(),
                    Forms\Components\Toggle::make('espuria')->label('Amostra espúria (fora da curva)')->helperText('Excluída da regressão quando marcada.'),
                    Forms\Components\TextInput::make('tipologia')->label('Tipologia')->maxLength(255),
                    Forms\Components\TextInput::make('padrao_cub')->label('Padrão CUB')->maxLength(255),
                    Forms\Components\Select::make('estado_conservacao')->label('Estado de Conservação')
                        ->options(['Bom' => 'Bom', 'Regular' => 'Regular', 'Ruim' => 'Ruim', 'Péssimo' => 'Péssimo']),
                    Forms\Components\TextInput::make('idade_aparente')->label('Idade Aparente (anos)')->numeric(),
                    Forms\Components\TextInput::make('area_terreno')->label('Área do Terreno (m²)')->numeric(),
                    Forms\Components\TextInput::make('area_edificacao')->label('Área Edificada (m²)')->numeric(),
                    Forms\Components\Textarea::make('observacao')->label('Observação')->rows(2)->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('valor_m2')->label('Valor m²')->money('BRL')->sortable(),
                Tables\Columns\TextColumn::make('tipologia')->label('Tipologia')->placeholder('—'),
                Tables\Columns\TextColumn::make('estado_conservacao')->label('Conservação')->badge()->placeholder('—'),
                Tables\Columns\IconColumn::make('espuria')->label('Espúria')->boolean()
                    ->trueColor('danger')->falseColor('success'),
                Tables\Columns\IconColumn::make('geo_json')->label('No Mapa')
                    ->getStateUsing(fn(PgvAmostra $r) => $r->geo_json !== null)->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('espuria')->label('Espúria?'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPgvAmostras::route('/')];
    }
}
