<?php

namespace App\Services\Exports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * PoC Antônio Carlos item 6 — exportação do Histórico de Operações (Auditoria).
 * Segue a convenção dos ExportServices (Excel via SimpleExcel + PDF via
 * pdf.default-report), respeitando os filtros aplicados na AuditoriaPage.
 */
class AuditoriaExportService
{
    /** @var array<int, string> memo p/ não repetir a busca do mesmo usuário excluído */
    private array $nomesExcluidos = [];

    /**
     * Nome do autor da operação. Usuário soft-deletado some do morphTo padrão
     * (viraria "Sistema" e a trilha perderia o autor) — recupera com withTrashed.
     */
    private function nomeCauser($atividade): string
    {
        if ($atividade->causer?->name) {
            return $atividade->causer->name;
        }

        if ($atividade->causer_type === \App\Models\User::class && $atividade->causer_id) {
            return $this->nomesExcluidos[$atividade->causer_id]
                ??= (\App\Models\User::withTrashed()->find($atividade->causer_id)?->name ?? 'Sistema');
        }

        return 'Sistema';
    }

    /**
     * "#id — identificador legível" do registro auditado (numero_lote p/ Lote,
     * name/nome/inscrição/texto p/ as demais). Usado pela coluna "Registro" da
     * AuditoriaPage e pelos exports. Registro excluído (soft delete) continua
     * resolvível: busca sem escopos globais (tenant + SoftDeletingScope) — a
     * linha da auditoria já foi filtrada pelo tenant na própria página.
     */
    public static function rotuloRegistro($atividade): string
    {
        $id = $atividade->subject_id;
        if (! $id) {
            return '-';
        }

        $subject = $atividade->subject;

        if (! $subject && $atividade->subject_type && class_exists($atividade->subject_type)) {
            try {
                $subject = $atividade->subject_type::query()->withoutGlobalScopes()->find($id);
            } catch (\Throwable $e) {
                $subject = null;
            }
        }

        $nome = $subject?->numero_lote
            ?? $subject?->inscricao_imobiliaria
            ?? $subject?->name
            ?? $subject?->nome
            ?? $subject?->texto
            ?? null;

        return $nome ? "#{$id} — {$nome}" : "#{$id}";
    }

    /**
     * Campos afetados, legíveis: "campo: antes → depois · campo2: valor · ...".
     * Geometria (geo/geo_json) NÃO despeja o GeoJSON — vira a nota do croqui.
     * Valores longos/arrays são compactados e truncados (papel não é banco).
     */
    public static function resumoAlteracoes($atividade): string
    {
        // 2026-09-05: coluna JSON explodida chave a chave, só o que mudou, com o rótulo do
        // campo do município — mesma fonte do modal "Ver detalhes" (AuditoriaDiffService).
        return \App\Services\Auditoria\AuditoriaDiffService::resumo($atividade);
    }

    private function linha($atividade): array
    {
        return [
            'Data / Hora' => $atividade->created_at?->format('d/m/Y H:i:s') ?? '-',
            'Usuário' => $this->nomeCauser($atividade),
            'Operação' => match ($atividade->event) {
                'created' => 'Criado',
                'updated' => 'Atualizado',
                'deleted' => 'Excluído',
                default => $atividade->event ?? '-',
            },
            'Entidade' => $atividade->subject_type ? class_basename($atividade->subject_type) : '-',
            'Registro' => self::rotuloRegistro($atividade),
            'Descrição' => match ($atividade->description) {
                'created' => 'Criado',
                'updated' => 'Atualizado',
                'deleted' => 'Excluído',
                default => (string) ($atividade->description ?? '-'),
            },
            'Alterações' => self::resumoAlteracoes($atividade),
        ];
    }

    public function exportToExcel(Collection $atividades)
    {
        $fileName = 'auditoria-'.now()->format('Y-m-d-His').'.xlsx';
        $path = storage_path('app/public/exports/');

        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }

        $filePath = $path.$fileName;

        $writer = SimpleExcelWriter::create($filePath);
        foreach ($atividades as $atividade) {
            $writer->addRow($this->linha($atividade));
        }
        $writer->close();

        return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
    }

    public function exportToPdf(Collection $atividades)
    {
        $fileName = 'auditoria-'.now()->format('Y-m-d-His').'.pdf';

        $headings = ['Data / Hora', 'Usuário', 'Operação', 'Entidade', 'Registro', 'Descrição', 'Alterações'];
        $data = $atividades->map(fn ($a) => array_values($this->linha($a)));

        $title = 'Histórico de Operações (Auditoria)';
        $pdf = Pdf::loadView('pdf.default-report', compact('data', 'headings', 'title'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print ($pdf->stream()), $fileName);
    }
}
