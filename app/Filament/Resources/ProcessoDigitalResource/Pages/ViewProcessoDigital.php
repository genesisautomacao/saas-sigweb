<?php

namespace App\Filament\Resources\ProcessoDigitalResource\Pages;

use App\Filament\Resources\ProcessoDigitalResource;
use App\Models\ProcessoAnexo;
use App\Services\Processo\ProcessoChecklistService;
use App\Services\Processo\ProcessoFormService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ViewProcessoDigital extends ViewRecord
{
    protected static string $resource = ProcessoDigitalResource::class;

    /**
     * "Avançar Processo" também DENTRO do processo — o analista aprova/reprova os anexos
     * no checklist e julga a etapa sem voltar para a lista. Mesmo schema/execução da ação
     * da Caixa de Entrada (ProcessoDigitalResource::avancarProcessoForm/executarAvancarProcesso).
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('avancar_processo')
                ->label('Avançar Processo')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->modalHeading('Avançar Processo')
                ->modalSubmitActionLabel('Confirmar')
                ->visible(fn () => ProcessoDigitalResource::podeAvancarProcesso($this->record))
                ->fillForm(fn () => ['dados_formulario' => $this->record->dados_formulario ?? []])
                ->form(fn () => ProcessoDigitalResource::avancarProcessoForm($this->record))
                ->action(function (array $data, Action $action) {
                    if (! ProcessoDigitalResource::executarAvancarProcesso($this->record, $data)) {
                        $action->halt(); // guard do checklist — mantém o modal aberto
                    }

                    $this->record->refresh();
                }),
        ];
    }

    /**
     * PD-2 — aprova um anexo individual do checklist (wire:click na blade processo-anexos).
     */
    public function aprovarAnexo(int $anexoId): void
    {
        $anexo = ProcessoAnexo::find($anexoId);

        if (! $anexo || ! ProcessoChecklistService::podeAnalisar($anexo, $this->record)) {
            Notification::make()
                ->danger()
                ->title('Não foi possível aprovar')
                ->body('Este documento não está disponível para análise nesta etapa.')
                ->send();

            return;
        }

        ProcessoChecklistService::marcar($anexo, 'aprovado', null, \Filament\Facades\Filament::auth()->id());

        Notification::make()
            ->success()
            ->title('Documento aprovado')
            ->body($anexo->nome_arquivo)
            ->send();
    }

    /**
     * PD-2 — desfaz a análise de um anexo (volta para 'pendente'). Só existe para corrigir
     * um clique errado: após a decisão, os botões Aprovar/Reprovar somem da lista.
     */
    public function desfazerAnaliseAnexo(int $anexoId): void
    {
        $anexo = ProcessoAnexo::find($anexoId);

        if (! $anexo || ! ProcessoChecklistService::podeAnalisar($anexo, $this->record)) {
            Notification::make()
                ->danger()
                ->title('Não foi possível desfazer')
                ->body('Este documento não está disponível para análise nesta etapa.')
                ->send();

            return;
        }

        ProcessoChecklistService::marcar($anexo, 'pendente', null, \Filament\Facades\Filament::auth()->id());

        Notification::make()
            ->success()
            ->title('Análise desfeita')
            ->body($anexo->nome_arquivo.' voltou para "aguardando análise".')
            ->send();
    }

    /**
     * PD-2 — reprova um anexo individual com observação obrigatória
     * (mountAction('reprovarAnexo', { anexoId }) na blade processo-anexos).
     */
    public function reprovarAnexoAction(): Action
    {
        return Action::make('reprovarAnexo')
            ->modalHeading('Reprovar documento')
            ->modalDescription(function (array $arguments) {
                $anexo = ProcessoAnexo::find($arguments['anexoId'] ?? null);

                return $anexo?->nome_arquivo;
            })
            ->modalSubmitActionLabel('Reprovar')
            ->modalWidth('lg')
            ->form([
                Forms\Components\Textarea::make('observacao')
                    ->label('Motivo da reprovação')
                    ->helperText('O cidadão verá exatamente este texto ao corrigir o documento.')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data, array $arguments) {
                $anexo = ProcessoAnexo::find($arguments['anexoId'] ?? null);

                if (! $anexo || ! ProcessoChecklistService::podeAnalisar($anexo, $this->record)) {
                    Notification::make()
                        ->danger()
                        ->title('Não foi possível reprovar')
                        ->body('Este documento não está disponível para análise nesta etapa.')
                        ->send();

                    return;
                }

                ProcessoChecklistService::marcar($anexo, 'reprovado', $data['observacao'], \Filament\Facades\Filament::auth()->id());

                Notification::make()
                    ->success()
                    ->title('Documento reprovado')
                    ->body('Ao reprovar a etapa, o motivo irá junto no parecer para o cidadão.')
                    ->send();
            });
    }

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

                    // --- COLUNA DA DIREITA (Localização) ---
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
                    ])->columnSpan(1),
                ]),

                // Documentos em LARGURA TOTAL — cada anexo numa linha: nome/badges à esquerda,
                // ações (Aprovar/Reprovar do checklist PD-2, Anotar…) à direita.
                Infolists\Components\Section::make('Documentos e PDFs')
                    ->icon('heroicon-o-paper-clip')
                    ->schema([
                        // ViewEntry (blade) em vez de TextEntry->html(): o sanitizador do Filament
                        // removeria o wire:click/<button> do "Excluir". A blade não é sanitizada.
                        Infolists\Components\ViewEntry::make('anexos')
                            ->hiddenLabel()
                            ->view('filament.infolists.processo-anexos'),
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
