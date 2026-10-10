<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BackupExecucaoResource\Pages;
use App\Models\BackupExecucao;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * INF-3 — histórico do backup diário dos bancos de produção (só leitura, só Master).
 * Quem grava é o script do servidor via `php artisan backup:registrar`.
 */
class BackupExecucaoResource extends Resource
{
    protected static ?string $model = BackupExecucao::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $modelLabel = 'Backup';

    protected static ?string $pluralModelLabel = 'Backups';

    protected static ?string $navigationGroup = 'Configurações Globais';

    protected static ?string $slug = 'backups';

    protected static ?int $navigationSort = 5;

    // ---- Acesso: exclusivo do Master, sem criar/editar/excluir (o registro é do script) ----

    public static function canViewAny(): bool
    {
        return Filament::auth()->user()?->isMaster() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /** Badge vermelho no menu = quantos bancos estão sem backup bem-sucedido há mais de 26 h. */
    public static function getNavigationBadge(): ?string
    {
        $atrasados = count(BackupExecucao::sistemasAtrasados());

        return $atrasados > 0 ? (string) $atrasados : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Banco(s) sem backup bem-sucedido há mais de '.BackupExecucao::HORAS_ATRASO.' h';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sistema')
                    ->label('Banco')
                    ->badge()
                    ->formatStateUsing(fn ($state) => BackupExecucao::SISTEMAS[$state] ?? $state),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => BackupExecucao::STATUS[$state] ?? $state)
                    ->color(fn ($state) => $state === 'sucesso' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('tamanho_bytes')
                    ->label('Tamanho')
                    ->formatStateUsing(fn ($state) => BackupExecucao::formatarTamanho($state)),
                Tables\Columns\TextColumn::make('duracao_segundos')
                    ->label('Duração')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : gmdate($state >= 3600 ? 'H:i:s' : 'i:s', $state)),
                Tables\Columns\TextColumn::make('arquivo')
                    ->label('Arquivo no R2')
                    ->copyable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('erro')
                    ->label('Erro')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->erro)
                    ->color('danger')
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('servidor')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('sistema')->label('Banco')->options(BackupExecucao::SISTEMAS),
                Tables\Filters\SelectFilter::make('status')->options(BackupExecucao::STATUS),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBackupExecucoes::route('/'),
        ];
    }
}
