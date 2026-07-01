<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class EstoqueMovimentacaoExportService
{
    public function exportToExcel(Collection $movimentacoes)
    {
        $fileName = 'movimentacoes-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $data = $movimentacoes->map(function ($mov) {
            // Concatena os itens (Ex: "5x Lâmpada, 10x Fio")
            $itensStr = $mov->itens->map(fn($i) => $i->quantity . 'x ' . ($i->produto->name ?? ''))->implode(', ');

            return [
                'ID' => $mov->sequential_id,
                'Tipo' => ucfirst($mov->type),
                'Origem' => $mov->origem?->name ?? '-',
                'Destino' => $mov->destino?->name ?? '-',
                'Itens Movimentados' => $itensStr,
                'Operador' => $mov->user?->name ?? 'Sistema',
                'Data' => $mov->created_at->format('d/m/Y H:i'),
                'Motivo/Obs' => $mov->observacao ?? '-',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $movimentacoes)
    {
        $fileName = 'movimentacoes-' . now()->format('Y-m-d-His') . '.pdf';
        
        $headings = ['ID', 'Tipo', 'Origem', 'Destino', 'Itens', 'Data'];

        $data = $movimentacoes->map(function ($mov) {
            $itensStr = $mov->itens->map(fn($i) => $i->quantity . 'x ' . ($i->produto->name ?? ''))->implode(', ');
            
            return [
                $mov->sequential_id,
                ucfirst($mov->type),
                $mov->origem?->name ?? '-',
                $mov->destino?->name ?? '-',
                $itensStr,
                $mov->created_at->format('d/m/Y H:i'),
            ];
        });

        $title = 'Relatório de Movimentações de Estoque';

        // SetPaper 'landscape' deixa o PDF deitado para caber a lista de itens tranquilamente
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $fileName);
    }

    public function exportToXml(Collection $movimentacoes)
    {
        $fileName = 'movimentacoes-' . now()->format('Y-m-d-His') . '.xml';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><movimentacoes/>');

        foreach ($movimentacoes as $mov) {
            $item = $xml->addChild('movimentacao');
            $item->addAttribute('id', (string) $mov->sequential_id);
            $item->addChild('tipo', htmlspecialchars(ucfirst($mov->type)));
            $item->addChild('operacao_interna', htmlspecialchars($mov->operacaoInterna->name ?? ''));
            $item->addChild('origem', htmlspecialchars($mov->origem?->name ?? ''));
            $item->addChild('destino', htmlspecialchars($mov->destino?->name ?? ''));
            $item->addChild('tipo_estoque_origem', htmlspecialchars($mov->tipoEstoqueOrigem->name ?? ''));
            $item->addChild('tipo_estoque_destino', htmlspecialchars($mov->tipoEstoqueDestino->name ?? ''));
            $item->addChild('operador', htmlspecialchars($mov->user?->name ?? 'Sistema'));
            $item->addChild('data', htmlspecialchars($mov->created_at?->format('d/m/Y H:i') ?? ''));
            $item->addChild('observacao', htmlspecialchars($mov->observacao ?? ''));

            $itens = $item->addChild('itens');
            foreach ($mov->itens as $mi) {
                $node = $itens->addChild('item');
                $node->addChild('produto', htmlspecialchars($mi->produto->name ?? ''));
                $node->addChild('lote', htmlspecialchars($mi->loteEstoque->numero_lote ?? ''));
                $node->addChild('quantidade', (string) $mi->quantity);
            }
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml->asXML();
        }, $fileName, ['Content-Type' => 'application/xml']);
    }
}