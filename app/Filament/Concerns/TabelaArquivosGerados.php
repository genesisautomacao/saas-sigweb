<?php

namespace App\Filament\Concerns;

use App\Models\ArquivoGerado;
use App\Services\ArquivosGerados\ArquivosGeradosService;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Number;

/**
 * R84-4 — tabela "Arquivos Gerados" (mesma no /app e no /admin). Atualiza sozinha
 * a cada 10 s enquanto houver pedido na fila/processando.
 */
trait TabelaArquivosGerados
{
    public static function tabelaArquivos(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll(fn ($livewire) => $livewire->getTableRecords()->contains(fn ($r) => in_array($r->status, ['na_fila', 'processando'], true)) ? '10s' : null)
            ->columns([
                Tables\Columns\TextColumn::make('titulo')
                    ->label('Arquivo')
                    ->description(fn (ArquivoGerado $r) => $r->mensagem)
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Pedido por')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Pedido em')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn (ArquivoGerado $r) => (ArquivoGerado::STATUS[$r->status]['label'] ?? $r->status)
                        .($r->status === 'processando' && $r->progresso ? " · {$r->progresso}%" : ''))
                    ->color(fn (string $state) => ArquivoGerado::STATUS[$state]['cor'] ?? 'gray'),
                Tables\Columns\TextColumn::make('tamanho')
                    ->label('Tamanho')
                    ->formatStateUsing(fn ($state) => $state ? Number::fileSize($state, 1) : '—'),
                Tables\Columns\TextColumn::make('expira_em')->label('Disponível até')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Situação')
                    ->options(collect(ArquivoGerado::STATUS)->map(fn ($s) => $s['label'])->all()),
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')->options(ArquivoGerado::TIPOS),
            ])
            ->actions([
                Tables\Actions\Action::make('baixar')
                    ->label('Baixar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->button()
                    ->visible(fn (ArquivoGerado $r) => $r->pronto())
                    ->url(fn (ArquivoGerado $r) => ArquivosGeradosService::urlDownload($r), shouldOpenInNewTab: true),
                Tables\Actions\DeleteAction::make()
                    ->label('Excluir')
                    ->visible(fn (ArquivoGerado $r) => $r->visivelPara(auth()->user()) && ! in_array($r->status, ['na_fila', 'processando'], true))
                    ->before(fn (ArquivoGerado $r) => ArquivosGeradosService::apagarArquivo($r)),
            ])
            ->emptyStateHeading('Nenhum arquivo gerado')
            ->emptyStateDescription('Relatórios e exportações pesados aparecem aqui quando ficam prontos. Cada arquivo fica disponível por '.ArquivoGerado::DIAS_VALIDADE.' dias.');
    }
}
