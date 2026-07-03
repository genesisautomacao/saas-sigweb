<?php

namespace App\Filament\Resources\ChamadoResource\Pages;

use App\Filament\Resources\ChamadoResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewChamado extends ViewRecord
{
    protected static string $resource = ChamadoResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Dados do Chamado')->schema([
                Infolists\Components\TextEntry::make('protocolo')->label('Protocolo')->weight('bold')->copyable(),
                Infolists\Components\TextEntry::make('categoria.nome')->label('Categoria')->badge()->placeholder('—'),
                Infolists\Components\TextEntry::make('fluxo.nome')->label('Fluxo')->placeholder('—'),
                Infolists\Components\TextEntry::make('faseAtual.nome')->label('Fase Atual')->badge()->color('info')->placeholder('—'),
                Infolists\Components\TextEntry::make('status')->label('Status')->badge(),
                Infolists\Components\TextEntry::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i'),
                Infolists\Components\TextEntry::make('descricao')->label('Descrição')->columnSpanFull()->placeholder('—'),
            ])->columns(3),

            Infolists\Components\Section::make('Solicitante')->schema([
                Infolists\Components\TextEntry::make('solicitante_nome')->label('Nome')->placeholder('—'),
                Infolists\Components\TextEntry::make('solicitante_telefone')->label('Telefone')->placeholder('—'),
                Infolists\Components\TextEntry::make('solicitante_email')->label('E-mail')->placeholder('—'),
            ])->columns(3),

            Infolists\Components\Section::make('Respostas do Boletim (item 172)')->schema([
                Infolists\Components\KeyValueEntry::make('respostas_boletim')
                    ->hiddenLabel()
                    // Achata valores-array (ex.: resposta de checkbox ['Noite','Sempre']) para string,
                    // senão o KeyValueEntry quebra em htmlspecialchars(array).
                    ->getStateUsing(fn ($record) => collect($record->respostas_boletim ?? [])
                        ->map(fn ($v) => is_array($v) ? implode(', ', $v) : (string) $v)
                        ->toArray()),
            ])->visible(fn ($record) => ! empty($record->respostas_boletim))->collapsible(),

            Infolists\Components\Section::make('Fotos (item 173)')->schema([
                Infolists\Components\ImageEntry::make('fotos')->hiddenLabel(),
            ])->visible(fn ($record) => ! empty($record->fotos))->collapsible(),

            Infolists\Components\Section::make('Observações / Anotações')->schema([
                Infolists\Components\TextEntry::make('observacoes')->label('Observações')->placeholder('—'),
                Infolists\Components\TextEntry::make('anotacoes')->label('Anotações internas')->placeholder('—'),
            ])->columns(2)->collapsible(),
        ]);
    }
}
