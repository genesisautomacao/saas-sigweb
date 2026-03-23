<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Collection;

class PgvExportService
{
    public function exportSetoresToExcel(Collection $setores)
    {
        $fileName = 'setores-fiscais-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');
        if (!File::isDirectory($path)) File::makeDirectory($path, 0755, true, true);

        $data = $setores->map(function ($setor) {
            return [
                'ID' => $setor->sequential_id,
                'Nome do Setor' => $setor->nome,
                'Regra Base' => $setor->parametro->nome_padrao ?? '-',
                'Valor m² Terreno' => $setor->parametro ? number_format($setor->parametro->valor_m2_terreno, 2, ',', '') : '0,00',
            ];
        });

        SimpleExcelWriter::create($path . $fileName)->addHeader(array_keys($data->first() ?? []))->addRows($data->toArray());
        return response()->download($path . $fileName)->deleteFileAfterSend(true);
    }

    public function exportSetoresToPdf(Collection $setores)
    {
        $fileName = 'setores-fiscais-' . now()->format('Y-m-d-His') . '.pdf';
        $headings = ['ID', 'Nome do Setor', 'Regra Base', 'Valor m² Terreno'];

        $data = $setores->map(function ($setor) {
            return [
                $setor->sequential_id,
                $setor->nome,
                $setor->parametro->nome_padrao ?? '-',
                $setor->parametro ? 'R$ ' . number_format($setor->parametro->valor_m2_terreno, 2, ',', '') : 'R$ 0,00',
            ];
        });

        $title = 'Relatório de Setores Fiscais (PGV)';
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));
        return response()->streamDownload(fn () => print($pdf->stream()), $fileName);
    }

    public function exportValoresToExcel(Collection $valores)
    {
        $fileName = 'historico-valores-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');
        if (!File::isDirectory($path)) File::makeDirectory($path, 0755, true, true);

        $data = $valores->map(function ($valor) {
            $unidades = $valor->lote ? $valor->lote->unidadesImobiliarias->pluck('codigo_imovel_tributario')->filter()->join(', ') : '';

            return [
                'ID' => $valor->sequential_id,
                'Ano' => $valor->ano_vigente,
                'Bairro' => $valor->lote->quadra->bairro->name ?? '-',
                'Lote' => $valor->lote->numero_lote ?? '-',
                'Unidades (Códigos)' => $unidades ?: '-',
                'Setor Fiscal' => $valor->setor->nome ?? '-',
                'Valor Terreno' => $valor->valor_terreno ? number_format($valor->valor_terreno, 2, ',', '') : '0,00',
                'Valor Edificação' => $valor->valor_edificacao ? number_format($valor->valor_edificacao, 2, ',', '') : '0,00',
                'Valor Total' => $valor->valor_total ? number_format($valor->valor_total, 2, ',', '') : '0,00',
            ];
        });

        SimpleExcelWriter::create($path . $fileName)->addHeader(array_keys($data->first() ?? []))->addRows($data->toArray());
        return response()->download($path . $fileName)->deleteFileAfterSend(true);
    }

    public function exportValoresToPdf(Collection $valores)
    {
        $fileName = 'historico-valores-' . now()->format('Y-m-d-His') . '.pdf';
        $headings = ['Ano', 'Bairro', 'Lote', 'Códigos Fiscais', 'Setor Fiscal', 'Total'];

        $data = $valores->map(function ($valor) {
            $unidades = $valor->lote ? $valor->lote->unidadesImobiliarias->pluck('codigo_imovel_tributario')->filter()->join("\n") : '-';

            return [
                $valor->ano_vigente,
                $valor->lote->quadra->bairro->name ?? '-',
                $valor->lote->numero_lote ?? '-',
                $unidades ?: '-', // Usa \n para quebrar linha se tiver muitas unidades no PDF
                $valor->setor->nome ?? '-',
                'R$ ' . number_format($valor->valor_total, 2, ',', ''),
            ];
        });

        $title = 'Relatório de Histórico de Valores Venais (PGV)';
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'));
        return response()->streamDownload(fn () => print($pdf->stream()), $fileName);
    }
}