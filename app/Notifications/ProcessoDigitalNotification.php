<?php

namespace App\Notifications;

use App\Models\ProcessoDigital;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail transacional enviado ao cidadão (requerente) a cada transição relevante do
 * seu processo digital (BPMN). O tipo de e-mail é definido por `$evento`:
 *
 *  - acao_necessaria → o processo caiu numa etapa do solicitante (precisa preencher/responder)
 *  - aprovado        → uma etapa foi aprovada e o processo avançou
 *  - correcao        → o processo foi reprovado/devolvido para correção (inclui o parecer)
 *  - concluido       → o processo foi finalizado
 *
 * ENVIO SÍNCRONO (inline na requisição, sem worker de fila) — mesmo espírito do
 * ExpoPushService. Guardamos só o ID do processo e re-buscamos em toMail() com
 * withoutGlobalScopes() (independe do tenant do Filament em contexto).
 *
 * Para colocar em fila no futuro: fazer a classe `implements ShouldQueue` + `use Queueable`
 * (o design com processoId + re-fetch já é seguro para serialização) e garantir um worker.
 */
class ProcessoDigitalNotification extends Notification
{
    public function __construct(
        public int $processoId,
        public string $evento,
        public ?string $parecer = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $processo = ProcessoDigital::withoutGlobalScopes()
            ->with(['fluxo', 'etapaAtual', 'tenant'])
            ->find($this->processoId);

        $tenant = $processo?->tenant;
        $tenantName = $tenant?->name ?? config('app.name');
        $brandColor = data_get($tenant?->data, 'color', '#3b82f6');
        $logoUrl = $tenant?->getFilamentAvatarUrl();

        $codigo = $processo?->codigo_processo ?: ('#' . $this->processoId);
        $fluxoNome = $processo?->fluxo?->nome ?? '—';
        $etapaNome = $processo?->etapaAtual?->nome;

        $copy = $this->copyPorEvento($codigo);

        $actionUrl = $this->urlDoProcesso();

        return (new MailMessage)
            ->subject($copy['subject'])
            ->from(config('mail.from.address'), $tenantName)
            ->view('emails.processo-notificacao', [
                'tenantName' => $tenantName,
                'brandColor' => $brandColor,
                'logoUrl' => $logoUrl,
                'citizenName' => $notifiable->name,
                'heading' => $copy['heading'],
                // "corpo" e NÃO "message": Laravel reserva $message (o Illuminate\Mail\Message) nas views de e-mail.
                'corpo' => $copy['message'],
                'processCode' => $codigo,
                'fluxoNome' => $fluxoNome,
                'etapaNome' => $etapaNome,
                'rejectReason' => $this->evento === 'correcao' ? $this->parecer : null,
                'actionUrl' => $actionUrl,
                'actionLabel' => $copy['actionLabel'],
            ]);
    }

    /** Textos (assunto/título/corpo/botão) por tipo de evento. */
    private function copyPorEvento(string $codigo): array
    {
        return match ($this->evento) {
            'acao_necessaria' => [
                'subject' => "Ação necessária no seu processo {$codigo}",
                'heading' => 'Sua ação é necessária',
                'message' => 'O seu processo avançou e agora depende de você. Acesse o portal para preencher e responder a etapa atual.',
                'actionLabel' => 'Responder etapa',
            ],
            'aprovado' => [
                'subject' => "Seu processo {$codigo} avançou de etapa",
                'heading' => 'Etapa aprovada!',
                'message' => 'Uma etapa do seu processo foi aprovada e ele seguiu para a próxima fase de análise. Avisaremos por aqui se precisarmos de algo.',
                'actionLabel' => 'Acompanhar processo',
            ],
            'correcao' => [
                'subject' => "Seu processo {$codigo} foi devolvido para correção",
                'heading' => 'Correção necessária',
                'message' => 'Seu processo foi analisado e devolvido para ajustes. Veja o motivo abaixo, faça as correções necessárias e reenvie.',
                'actionLabel' => 'Corrigir processo',
            ],
            'concluido' => [
                'subject' => "Seu processo {$codigo} foi concluído",
                'heading' => 'Processo concluído',
                'message' => 'Seu processo foi finalizado com sucesso. Você pode consultar todos os detalhes pelo portal.',
                'actionLabel' => 'Ver processo',
            ],
            default => [
                'subject' => "Atualização no seu processo {$codigo}",
                'heading' => 'Atualização do processo',
                'message' => 'Houve uma atualização no seu processo. Acesse o portal para mais detalhes.',
                'actionLabel' => 'Ver processo',
            ],
        };
    }

    /** Link absoluto para a tela "Acompanhar" do processo no painel do cidadão. */
    private function urlDoProcesso(): ?string
    {
        try {
            return \App\Filament\Cidadao\Resources\ProcessoDigitalResource::getUrl(
                'view',
                ['record' => $this->processoId],
                panel: 'cidadao',
            );
        } catch (\Throwable $e) {
            return null;
        }
    }
}
