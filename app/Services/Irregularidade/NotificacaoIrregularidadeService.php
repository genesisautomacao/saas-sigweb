<?php

namespace App\Services\Irregularidade;

use App\Models\Lote;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;

class NotificacaoIrregularidadeService
{
    public function generatePdf(Lote $lote)
    {
        $tenant  = Filament::getTenant() ?? $lote->tenant;
        $unidade = $lote->unidadesImobiliarias()->with('proprietario')->first();

        $proprietario = $unidade?->proprietario?->name
            ?? ($unidade?->dados_tributarios['proprietario_name'] ?? null)
            ?? 'Proprietário não identificado';

        $enderecoLote = implode(', ', array_filter([
            $unidade?->logradouro_nome,
            $unidade?->numero_imovel ? 'nº ' . $unidade->numero_imovel : null,
            $unidade?->complemento,
        ])) ?: ('Lote ' . $lote->numero_lote);

        $dataHora = now()->format('d/m/Y H:i:s');
        $dataExtenso = \Carbon\Carbon::now()->translatedFormat('d \d\e F \d\e Y');

        $fileName = 'notificacao-irregularidade-lote-' . $lote->numero_lote . '.pdf';

        $pdf = Pdf::loadView('pdf.notificacao-irregularidade', compact(
            'lote', 'proprietario', 'enderecoLote', 'tenant', 'dataHora', 'dataExtenso'
        ));
        $pdf->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }
}
