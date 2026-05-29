<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelWriter;

class ProdutividadeExportService
{
    public function exportToExcel(Collection $lotes)
    {
        $fileName = 'coletas-produtividade-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/exports/');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path . $fileName;

        $statusLabels = [
            'nao_visitado'   => 'Não Visitado',
            'coletado'       => 'Coletado',
            'pendente'       => 'Pendente',
            'inconformidade' => 'Inconformidade',
        ];

        $ocupacaoLabels = [
            'baldio'     => 'Baldio',
            'construido' => 'Construído',
        ];

        $situacaoLabels = [
            'meio_quadra' => 'Meio de Quadra',
            'esquina'     => 'Esquina',
            'encravado'   => 'Encravado',
        ];

        $data = $lotes->map(function ($lote) use ($statusLabels, $ocupacaoLabels, $situacaoLabels) {
            return [
                'ID'                  => $lote->sequential_id,
                'Lote nº'             => $lote->numero_lote ?? '—',
                'Quadra'              => $lote->quadra->name ?? '—',
                'Zona'                => $lote->zona->sigla ?? '—',
                'Status'              => $statusLabels[$lote->status_cadastro] ?? '—',
                'Ocupação'            => $ocupacaoLabels[$lote->ocupacao] ?? '—',
                'Situação na Quadra'  => $situacaoLabels[$lote->situacao_quadra] ?? '—',
                'Cadastrador'         => $lote->coletor->name ?? '—',
                'Data Coleta'         => $lote->coletado_em ? $lote->coletado_em->format('d/m/Y H:i') : '—',
                'Área Lote (m²)'      => $lote->area_geo ? number_format($lote->area_geo, 2, ',', '') : '0,00',
                'Testada (m)'         => $lote->main_facade_length ? number_format($lote->main_facade_length, 2, ',', '') : '0,00',
                'Observação'          => $lote->observacao ?? '',
                'Total Unidades'      => $lote->unidadesImobiliarias->count(),
                'Total Edificações'   => $lote->edificacoes->count(),
                'Foto Frontal'        => $lote->foto_frontal ? 'Sim' : 'Não',
                'Foto Lat. Esq.'      => $lote->foto_lateral_esq ? 'Sim' : 'Não',
                'Foto Lat. Dir.'      => $lote->foto_lateral_dir ? 'Sim' : 'Não',
                'Inconformidade'      => $lote->status_cadastro === 'inconformidade'
                    ? ($lote->inconformidade_descricao ?? '')
                    : '',
            ];
        });

        SimpleExcelWriter::create($filePath)
            ->addHeader(array_keys($data->first() ?? []))
            ->addRows($data->toArray());

        return response()->download($filePath)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $lotes)
    {
        $dataHora = now()->format('d/m/Y H:i:s');
        $tenant   = Filament::getTenant();
        $fileName = 'coletas-produtividade-' . now()->format('YmdHis') . '.pdf';

        // Pré-processa as 3 fotos para base64 (DomPDF não acessa Storage URL em produção)
        $lotes->each(function ($lote) {
            $lote->_fotos_b64 = [
                'frontal'     => $this->fotoToBase64($lote->foto_frontal),
                'lateral_esq' => $this->fotoToBase64($lote->foto_lateral_esq),
                'lateral_dir' => $this->fotoToBase64($lote->foto_lateral_dir),
            ];
        });

        $pdf = Pdf::loadView('pdf.produtividade-detalhado', compact('lotes', 'tenant', 'dataHora'));
        $pdf->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $fileName);
    }

    /**
     * Converte um caminho de foto do disco 'public' em data URI base64.
     * Retorna null se o caminho for vazio ou arquivo inexistente.
     */
    private function fotoToBase64(?string $path): ?string
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path);
        return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($path));
    }
}
