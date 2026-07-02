<?php

namespace App\Filament\Resources\ProcessoDigitalResource\Pages;

use App\Filament\Resources\ProcessoDigitalResource;
use App\Services\Processo\ProcessoFormService;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;

class ViewProcessoDigital extends ViewRecord
{
    protected static string $resource = ProcessoDigitalResource::class;

    /**
     * Exclui uma CÓPIA ANOTADA deste processo (item 222). Só permite `tipo_anexo = 'anotado'`
     * e do próprio processo — originais/uploads do cidadão são preservados. Chamado via wire:click.
     */
    public function excluirAnexoAnotado(int $anexoId): void
    {
        $anexo = \App\Models\ProcessoAnexo::find($anexoId);

        if (! $anexo || (int) $anexo->processo_digital_id !== (int) $this->record->id || $anexo->tipo_anexo !== 'anotado') {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Não foi possível excluir')
                ->body('Só é possível excluir cópias anotadas deste processo.')
                ->send();
            return;
        }

        if ($anexo->caminho_arquivo) {
            Storage::disk('public')->delete($anexo->caminho_arquivo);
        }
        $anexo->delete();

        \Filament\Notifications\Notification::make()
            ->success()
            ->title('Cópia anotada excluída')
            ->send();
    }

    // Montamos a tela de leitura de dados (Infolist) para o Analista
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Grid::make(3)->schema([
                    
                    // --- COLUNA DA ESQUERDA (Dados do Cidadão e Formulário) ---
                    Infolists\Components\Group::make()->schema([
                        Infolists\Components\Section::make('Informações do Solicitante')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Infolists\Components\TextEntry::make('requerente.name')
                                    ->label('Nome Completo'),
                                Infolists\Components\TextEntry::make('requerente.email')
                                    ->label('E-mail de Contato'),
                                // Item 214 — CPF e telefone vêm da Pessoa vinculada (User→Pessoa)
                                Infolists\Components\TextEntry::make('requerente_cpf')
                                    ->label('CPF')
                                    ->state(fn ($record) => $record->requerente?->pessoaNoTenant($record->tenant_id)?->cpf ?: '—'),
                                Infolists\Components\TextEntry::make('requerente_telefone')
                                    ->label('Telefone')
                                    ->state(fn ($record) => $record->requerente?->pessoaNoTenant($record->tenant_id)?->telefone ?: '—'),
                                Infolists\Components\TextEntry::make('codigo_processo')
                                    ->label('Nº do Protocolo')
                                    ->badge()
                                    ->color('info'),
                            ])->columns(3),

                        Infolists\Components\Section::make('Respostas por Etapa')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Infolists\Components\TextEntry::make('respostas_por_etapa')
                                    ->hiddenLabel()
                                    ->html()
                                    ->state(fn ($record) => new HtmlString(ProcessoFormService::respostasHtml($record))),
                            ]),
                    ])->columnSpan(2),

                    // --- COLUNA DA DIREITA (Localização e Anexos) ---
                    Infolists\Components\Group::make()->schema([
                        Infolists\Components\Section::make('Imóvel Vinculado')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                // Itens 130/141/221 — cadastro/inscrição/localização + loteamento/quadra/nº
                                Infolists\Components\TextEntry::make('dados_imovel')
                                    ->hiddenLabel()
                                    ->html()
                                    ->state(fn ($record) => new HtmlString(ProcessoFormService::dadosImovelHtml($record->lote_id))),
                            ]),

                        Infolists\Components\Section::make('Documentos e PDFs')
                            ->icon('heroicon-o-paper-clip')
                            ->schema([
                                // ViewEntry (blade) em vez de TextEntry->html(): o sanitizador do Filament
                                // removeria o wire:click/<button> do "Excluir". A blade não é sanitizada.
                                Infolists\Components\ViewEntry::make('anexos')
                                    ->hiddenLabel()
                                    ->view('filament.infolists.processo-anexos'),
                            ]),
                    ])->columnSpan(1),
                ]),

                // Item 216 — histórico de fases com todas as interações
                Infolists\Components\Section::make('Histórico de Tramitação')
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->schema([
                        Infolists\Components\TextEntry::make('historico')
                            ->hiddenLabel()
                            ->html()
                            ->state(fn ($record) => new HtmlString(ProcessoFormService::historicoHtml($record))),
                    ]),
            ]);
    }
}