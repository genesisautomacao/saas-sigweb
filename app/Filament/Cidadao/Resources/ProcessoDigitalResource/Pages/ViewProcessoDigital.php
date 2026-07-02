<?php

namespace App\Filament\Cidadao\Resources\ProcessoDigitalResource\Pages;

use App\Filament\Cidadao\Resources\ProcessoDigitalResource;
use App\Services\Processo\ProcessoFormService;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

/**
 * Tela "Acompanhar" do cidadão (somente leitura), no mesmo espírito da visão dos setores:
 * visão geral, imóvel vinculado, respostas por etapa, documentos e histórico de tramitação.
 * NÃO reabre o wizard de criação (o cidadão não pode trocar tipo/lote depois de solicitado).
 */
class ViewProcessoDigital extends ViewRecord
{
    protected static string $resource = ProcessoDigitalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Só aparece quando o processo está com o cidadão (correção/resposta)
            Actions\EditAction::make()
                ->label(fn () => $this->record->status === 'pendente_correcao' ? 'Corrigir Processo' : 'Responder Etapa')
                ->color(fn () => $this->record->status === 'pendente_correcao' ? 'danger' : 'primary')
                ->visible(fn () => in_array($this->record->status, ProcessoDigitalResource::STATUS_EDITAVEIS)),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Visão Geral')
                ->icon('heroicon-o-clipboard-document-check')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('codigo_processo')->label('Protocolo')->badge()->color('info'),
                    Infolists\Components\TextEntry::make('fluxo.nome')->label('Serviço'),
                    Infolists\Components\TextEntry::make('etapaAtual.nome')->label('Fase Atual')->badge()->color('warning')->default('Aguardando triagem'),
                    Infolists\Components\TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->colors([
                            'gray' => 'rascunho',
                            'primary' => 'em_andamento',
                            'info' => 'aguardando_solicitante',
                            'success' => 'concluido',
                            'danger' => ['pendente_correcao', 'cancelado'],
                        ]),
                ]),

            Infolists\Components\Section::make('Imóvel Vinculado')
                ->icon('heroicon-o-map-pin')
                ->visible(fn ($record) => (bool) $record->lote_id)
                ->schema([
                    Infolists\Components\TextEntry::make('imovel')
                        ->hiddenLabel()
                        ->html()
                        ->state(fn ($record) => new HtmlString(ProcessoFormService::dadosImovelHtml($record->lote_id))),
                ]),

            Infolists\Components\Section::make('Respostas Enviadas')
                ->icon('heroicon-o-clipboard-document-list')
                ->collapsible()
                ->schema([
                    Infolists\Components\TextEntry::make('respostas')
                        ->hiddenLabel()
                        ->html()
                        ->state(fn ($record) => new HtmlString(ProcessoFormService::respostasHtml($record))),
                ]),

            Infolists\Components\Section::make('Documentos Anexados')
                ->icon('heroicon-o-paper-clip')
                ->collapsible()
                ->schema([
                    Infolists\Components\TextEntry::make('documentos')
                        ->hiddenLabel()
                        ->html()
                        ->state(fn ($record) => new HtmlString(ProcessoFormService::documentosHtml($record))),
                ]),

            Infolists\Components\Section::make('Histórico do Processo')
                ->icon('heroicon-o-clock')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Infolists\Components\TextEntry::make('historico')
                        ->hiddenLabel()
                        ->html()
                        ->state(fn ($record) => new HtmlString(ProcessoFormService::historicoHtml($record))),
                ]),
        ]);
    }
}
