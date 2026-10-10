<?php

namespace App\Filament\Admin\Resources\BackupExecucaoResource\Pages;

use App\Filament\Admin\Resources\BackupExecucaoResource;
use App\Models\BackupExecucao;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListBackupExecucoes extends ListRecords
{
    protected static string $resource = BackupExecucaoResource::class;

    /** Situação de cada banco: último backup bem-sucedido e alerta de atraso (> 26 h). */
    public function getSubheading(): string|Htmlable|null
    {
        $linhas = [];
        foreach (BackupExecucao::SISTEMAS as $chave => $rotulo) {
            $ultimo = BackupExecucao::ultimoSucesso($chave);
            $atrasado = $ultimo === null || $ultimo->lt(now()->subHours(BackupExecucao::HORAS_ATRASO));
            $quando = $ultimo
                ? $ultimo->format('d/m/Y H:i').' ('.$ultimo->diffForHumans().')'
                : 'nenhum registrado';
            $cor = $atrasado ? '#dc2626' : '#16a34a';
            $icone = $atrasado ? '⚠' : '✓';
            $linhas[] = '<span style="color:'.$cor.'; font-weight:600">'.$icone.' '.e($rotulo).'</span>: último backup bem-sucedido '.e($quando);
        }

        return new HtmlString(
            implode('<br>', $linhas)
            .'<br><span style="font-size:12px; opacity:.75">Diário às 3h · 30 dias guardados e trancados no R2 (sigweb-backup / lider-backup) · alerta de falha: ApiSetting "Backup" → ALERTA_EMAIL</span>'
        );
    }
}
