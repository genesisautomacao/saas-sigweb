<?php

namespace App\Filament\Resources\LogradouroResource\RelationManagers;

use App\Filament\Resources\SecaoLogradouroResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SecoesRelationManager extends RelationManager
{
    protected static string $relationship = 'secoes';
    protected static ?string $title = 'Seções de Logradouro';
    protected static ?string $icon = 'heroicon-o-minus';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('codigo')
                ->label('Código da Seção (métrico)')
                ->maxLength(50),
            // Item 44 do edital — lista do sistema, rótulo white-label
            \App\Services\Coleta\CampoDominioService::aplicar(
                Forms\Components\Select::make('lado')->placeholder('Selecione...')->nullable(),
                'secao_logradouro', 'lado'
            ),
            Forms\Components\TextInput::make('name')
                ->label('Nome / Identificação da Seção')
                ->maxLength(255),
            // Refatoração PoC Tangará: tipo_pavimentacao é campo customizado (kit)
            ...\App\Services\Coleta\CampoCustomizadoService::componentes('secao_logradouro'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('codigo')->label('Código')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('lado')
                    ->label('Lado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => \App\Services\Coleta\CampoDominioService::rotuloValor('secao_logradouro', 'lado', $state) ?? '—'),
                Tables\Columns\TextColumn::make('dados_customizados.tipo_pavimentacao')
                    ->label('Pavimentação')
                    ->badge()
                    ->default('—'),
                Tables\Columns\TextColumn::make('extensao_geo')
                    ->label('Extensão (m)')
                    ->numeric(2, ',', '.')
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nova Seção')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['code'] = (string) \Illuminate\Support\Str::uuid();
                        $data['tenant_id'] = \Filament\Facades\Filament::getTenant()->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_no_mapa')
                    ->label('Mapa')
                    ->icon('heroicon-o-map')
                    ->color('info')
                    ->url(function ($record) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        $row = \Illuminate\Support\Facades\DB::table('secoes_logradouro')
                            ->selectRaw('ST_X(ST_PointOnSurface(geo)) AS lon, ST_Y(ST_PointOnSurface(geo)) AS lat')
                            ->where('id', $record->id)
                            ->first();
                        if ($row && $row->lat && $row->lon) {
                            return url('/app/' . $tenant->slug . '/mapa-interativo?layer=secoes_logradouro&focus_lat=' . $row->lat . '&focus_lon=' . $row->lon . '&zoom=18');
                        }
                        return null;
                    })
                    ->visible(fn($record) => $record->geo_json !== null),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
