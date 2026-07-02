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
                                Infolists\Components\TextEntry::make('anexos_renderizados')
                                    ->hiddenLabel()
                                    ->html()
                                    ->default(function ($record) {
                                        // Busca os anexos salvos na tabela pivô
                                        $anexos = \App\Models\ProcessoAnexo::where('processo_digital_id', $record->id)->get();
                                        
                                        if ($anexos->isEmpty()) {
                                            return '<span class="text-gray-500 italic">Nenhum documento anexado.</span>';
                                        }
                                        
                                        $html = '<ul class="space-y-3">';
                                        foreach ($anexos as $anexo) {
                                            $url = Storage::url($anexo->caminho_arquivo);
                                            $ehPdf = str_ends_with(strtolower($anexo->nome_arquivo), '.pdf');
                                            $icone = $ehPdf ? '📕' : '🖼️';
                                            $tag = $anexo->tipo_anexo === 'anotado'
                                                ? " <span class='text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-800'>anotado v{$anexo->versao}</span>"
                                                : '';

                                            // Item 222 — anotar PDF (abre o editor; salva uma cópia sem sobrescrever)
                                            $anotar = $ehPdf
                                                ? " · <a href='" . route('processo-anexo.anotar', $anexo) . "' target='_blank' class='text-amber-600 hover:text-amber-800 underline'>✏️ Anotar</a>"
                                                : '';

                                            $html .= "<li class='flex items-center gap-2 flex-wrap'>
                                                <a href='{$url}' target='_blank' class='text-primary-600 hover:text-primary-800 underline font-semibold'>
                                                    {$icone} {$anexo->nome_arquivo}
                                                </a>{$tag}{$anotar}
                                            </li>";
                                        }
                                        $html .= '</ul>';

                                        return new HtmlString($html);
                                    })
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