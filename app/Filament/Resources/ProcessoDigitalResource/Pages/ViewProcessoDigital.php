<?php

namespace App\Filament\Resources\ProcessoDigitalResource\Pages;

use App\Filament\Resources\ProcessoDigitalResource;
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
                                Infolists\Components\TextEntry::make('codigo_processo')
                                    ->label('Nº do Protocolo')
                                    ->badge()
                                    ->color('info'),
                            ])->columns(3),

                        Infolists\Components\Section::make('Respostas do Formulário Dinâmico')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Infolists\Components\KeyValueEntry::make('dados_formulario')
                                    ->hiddenLabel()
                                    ->keyLabel('Pergunta / Campo Exigido')
                                    ->valueLabel('Resposta do Munícipe')
                                    // 🛑 A VACINA CORRETA: Usa ->state() para Infolists
                                    ->state(function ($record) {
                                        $dados = $record->dados_formulario;
                                        if (!is_array($dados)) return [];
                                        
                                        $formatado = [];
                                        foreach ($dados as $key => $value) {
                                            // Se o valor for um Array (Checkbox), junta com vírgula. Se não, mostra o texto normal.
                                            $formatado[$key] = is_array($value) ? implode(', ', $value) : $value;
                                        }
                                        return $formatado;
                                    }),
                            ]),
                    ])->columnSpan(2),

                    // --- COLUNA DA DIREITA (Localização e Anexos) ---
                    Infolists\Components\Group::make()->schema([
                        Infolists\Components\Section::make('Localização Vinculada')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Infolists\Components\TextEntry::make('lote.numero_lote')
                                    ->label('Lote Selecionado no Mapa')
                                    ->badge()
                                    ->color('success')
                                    ->default('Nenhum lote vinculado'),
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
                                        
                                        $html = '<ul class="list-disc pl-4 space-y-3">';
                                        foreach ($anexos as $anexo) {
                                            // Gera um link provisório para o arquivo
                                            $url = Storage::url($anexo->caminho_arquivo);
                                            $icone = str_ends_with($anexo->nome_arquivo, '.pdf') ? '📕' : '🖼️';
                                            
                                            $html .= "<li>
                                                <a href='{$url}' target='_blank' class='text-primary-600 hover:text-primary-800 underline font-semibold flex items-center gap-2'>
                                                    {$icone} {$anexo->nome_arquivo}
                                                </a>
                                            </li>";
                                        }
                                        $html .= '</ul>';
                                        
                                        return new HtmlString($html);
                                    })
                            ]),
                    ])->columnSpan(1),
                ])
            ]);
    }
}