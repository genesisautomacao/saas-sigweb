<?php

namespace App\Console\Commands;

use App\Models\ApiSetting;
use App\Models\BackupExecucao;
use App\Notifications\BackupFalhaNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * INF-3 — chamado pelo script de backup do servidor (/root/backup-diario.sh) ao fim de
 * cada banco. Grava a execução (tela "Backups" do /admin) e, se falhou, envia e-mail
 * para o ApiSetting "Backup" → ALERTA_EMAIL (vários endereços separados por vírgula).
 *
 *   php artisan backup:registrar webgis sucesso --arquivo=sigweb-backup/banco/2026-10-10.dump --tamanho=48234567 --duracao=42
 *   php artisan backup:registrar ferramenta falha --erro="mysqldump: ..."
 */
class BackupRegistrar extends Command
{
    protected $signature = 'backup:registrar
        {sistema : webgis | ferramenta}
        {status : sucesso | falha}
        {--arquivo= : bucket/caminho do arquivo no R2}
        {--tamanho= : tamanho em bytes}
        {--duracao= : duração em segundos}
        {--erro= : mensagem de erro (falha)}
        {--servidor= : nome do servidor (padrão: hostname)}';

    protected $description = 'Registra uma execução do backup diário (e alerta por e-mail se falhou)';

    public function handle(): int
    {
        $sistema = $this->argument('sistema');
        $status = $this->argument('status');

        if (! isset(BackupExecucao::SISTEMAS[$sistema]) || ! isset(BackupExecucao::STATUS[$status])) {
            $this->error('Uso: backup:registrar {webgis|ferramenta} {sucesso|falha}');

            return self::INVALID;
        }

        $execucao = BackupExecucao::create([
            'sistema' => $sistema,
            'status' => $status,
            'arquivo' => $this->option('arquivo') ?: null,
            'tamanho_bytes' => is_numeric($this->option('tamanho')) ? (int) $this->option('tamanho') : null,
            'duracao_segundos' => is_numeric($this->option('duracao')) ? (int) $this->option('duracao') : null,
            'erro' => $this->option('erro') ? mb_substr($this->option('erro'), 0, 5000) : null,
            'servidor' => $this->option('servidor') ?: gethostname(),
        ]);

        $this->info("Registrado: {$sistema} {$status} (#{$execucao->id})");

        if ($status === 'falha') {
            $this->alertar($execucao);
        }

        return self::SUCCESS;
    }

    private function alertar(BackupExecucao $execucao): void
    {
        $destinos = collect(explode(',', (string) data_get(ApiSetting::where('name', 'Backup')->value('data'), 'ALERTA_EMAIL')))
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->values();

        if ($destinos->isEmpty()) {
            $this->warn('Sem e-mail de alerta: cadastre o ApiSetting "Backup" com ALERTA_EMAIL no /admin.');

            return;
        }

        try {
            Notification::route('mail', $destinos->all())->notify(new BackupFalhaNotification($execucao->id));
            $this->info('Alerta enviado para: '.$destinos->implode(', '));
        } catch (\Throwable $e) {
            Log::warning('backup:registrar — falha ao enviar o alerta', ['erro' => $e->getMessage()]);
            $this->warn('Não consegui enviar o alerta: '.$e->getMessage());
        }
    }
}
