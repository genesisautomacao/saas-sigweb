<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ViabilidadeEmissaoResource\Pages;
use App\Models\ViabilidadeEmissao;
use App\Services\Viabilidade\ViabilidadePdfService;
use App\Traits\HasTenantModule;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ViabilidadeEmissaoResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'pgv';

    protected static ?string $model = ViabilidadeEmissao::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationGroup = 'Consultas de Viabilidade';
    protected static ?string $navigationLabel = 'Histórico de Emissões';
    protected static ?string $modelLabel = 'Emissão';
    protected static ?string $pluralModelLabel = 'Histórico de Emissões';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('protocolo')
                    ->label('Protocolo')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Protocolo copiado!')
                    ->fontFamily('mono')
                    ->weight('bold'),

                Tables\Columns\BadgeColumn::make('tipo')
                    ->label('Tipo')
                    ->colors([
                        'primary'   => 'viabilidade',
                        'warning'   => 'parcelamento',
                        'success'   => 'unificacao',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'viabilidade'  => 'Viabilidade',
                        'parcelamento' => 'Parcelamento',
                        'unificacao'   => 'Unificação',
                        default        => ucfirst($state),
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Resultado')
                    ->colors([
                        'success' => 'permitido',
                        'warning' => 'permissivel',
                        'danger'  => 'proibido',
                    ])
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'permitido'   => 'Permitido',
                        'permissivel' => 'Permissível',
                        'proibido'    => 'Proibido',
                        default       => $state ?? '—',
                    }),

                Tables\Columns\TextColumn::make('numero_lote')
                    ->label('Lote')
                    ->searchable()
                    ->default('—'),

                Tables\Columns\TextColumn::make('emissor.name')
                    ->label('Emitido por')
                    ->default('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Emitido em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        'viabilidade'  => 'Viabilidade',
                        'parcelamento' => 'Parcelamento',
                        'unificacao'   => 'Unificação',
                    ]),

                Tables\Filters\Filter::make('periodo')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('de')->label('De'),
                        \Filament\Forms\Components\DatePicker::make('ate')->label('Até'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['de'],  fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                            ->when($data['ate'], fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('reimprimir')
                    ->label('Reimprimir PDF')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->action(fn (ViabilidadeEmissao $record) => app(ViabilidadePdfService::class)->reimprimirPdf($record)),

                Tables\Actions\Action::make('validar')
                    ->label('Ver URL de Validação')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->url(fn (ViabilidadeEmissao $record) => url("/v/{$record->protocolo}"))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Nenhuma emissão registrada')
            ->emptyStateDescription('As consultas de viabilidade emitidas pelo mapa aparecerão aqui.')
            ->emptyStateIcon('heroicon-o-document-magnifying-glass');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListViabilidadeEmissoes::route('/'),
        ];
    }
}
