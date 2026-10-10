<?php

namespace App\Notifications;

use App\Models\BackupExecucao;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * INF-3 — avisa que o backup diário de um banco de produção falhou.
 * Destinatário avulso (Notification::route('mail', ...)) = ApiSetting "Backup" → ALERTA_EMAIL.
 * Envio SÍNCRONO, mesmo padrão das demais notificações (sem worker de fila).
 */
class BackupFalhaNotification extends Notification
{
    public function __construct(public int $execucaoId) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $execucao = BackupExecucao::findOrFail($this->execucaoId);
        $sistema = BackupExecucao::SISTEMAS[$execucao->sistema] ?? $execucao->sistema;

        return (new MailMessage)
            ->error()
            ->subject("FALHA no backup: {$sistema} — ".$execucao->created_at->format('d/m/Y H:i'))
            ->greeting('Backup diário falhou')
            ->line("Sistema: {$sistema}")
            ->line('Servidor: '.($execucao->servidor ?: '—'))
            ->line('Quando: '.$execucao->created_at->format('d/m/Y H:i'))
            ->line('Erro: '.($execucao->erro ?: '(sem detalhe)'))
            ->line('Log completo no servidor: /var/log/backup-diario.log')
            ->action('Ver backups no painel', url('/admin/backups'));
    }
}
