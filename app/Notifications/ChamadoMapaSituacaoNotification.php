<?php

namespace App\Notifications;

use App\Models\ChamadoMapa;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * R80-1 — avisa o cidadão que a situação do chamado (aberto pelo mapa público) mudou.
 * Destinatário anônimo (Notification::route('mail', ...)): o cidadão não tem conta.
 * Envio SÍNCRONO, mesmo padrão do ProcessoDigitalNotification (sem worker de fila).
 */
class ChamadoMapaSituacaoNotification extends Notification
{
    public function __construct(public int $chamadoId) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $chamado = ChamadoMapa::withoutGlobalScopes()->with('tenant')->findOrFail($this->chamadoId);
        $tenant = $chamado->tenant;
        $tenantName = $tenant?->name ?? config('app.name');
        $situacao = ChamadoMapa::rotuloSituacao($chamado->situacao);

        return (new MailMessage)
            ->subject("Seu chamado {$chamado->protocolo}: {$situacao}")
            ->from(config('mail.from.address'), $tenantName)
            ->view('emails.chamado-mapa-situacao', [
                'tenantName' => $tenantName,
                'brandColor' => data_get($tenant?->data, 'color', '#3b82f6'),
                'logoUrl' => $tenant?->getFilamentAvatarUrl(),
                'nome' => $chamado->nome,
                'protocolo' => $chamado->protocolo,
                'assunto' => ChamadoMapa::ASSUNTOS[$chamado->assunto] ?? $chamado->assunto,
                'titulo' => $chamado->titulo,
                'abertoEm' => $chamado->created_at?->format('d/m/Y H:i'),
                'situacao' => $situacao,
                'corSituacao' => ChamadoMapa::corSituacao($chamado->situacao),
                // "resposta" e não "message" (reservada pelo Laravel nas views de e-mail).
                'resposta' => $chamado->resposta,
            ]);
    }
}
