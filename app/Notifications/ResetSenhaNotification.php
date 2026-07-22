<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Redefinição de senha em PT-BR, com a marca do município (brasão + cor) e envio SÍNCRONO.
 *
 * Substitui a Filament\Notifications\Auth\ResetPassword via bind no AppServiceProvider:
 * a original é ShouldQueue e, com QUEUE_CONNECTION=database SEM worker rodando (padrão
 * deste projeto — envio de e-mail é síncrono, ver ProcessoDigitalNotification), o e-mail
 * ficava preso na fila e nunca saía. Esta versão dispara inline via mailer default
 * (Resend, injetado globalmente pelo AppServiceProvider a partir do ApiSetting "Resend").
 */
class ResetSenhaNotification extends BaseResetPassword
{
    /** URL de redefinição — preenchida pelo Filament (RequestPasswordReset) após instanciar. */
    public string $url;

    public function toMail($notifiable): MailMessage
    {
        $tenant = $notifiable->tenants()->first();

        $tenantName = $tenant?->name ?? config('app.name');
        $brandColor = (string) data_get($tenant?->data, 'color', '#3b82f6');
        $logoUrl = $tenant?->getFilamentAvatarUrl();

        $url = isset($this->url)
            ? $this->url
            : url(route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()], false));

        $expiraMinutos = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->from(config('mail.from.address'), $tenantName)
            ->subject('Redefinição de senha — '.$tenantName)
            ->view('emails.reset-senha', [
                'tenantName' => $tenantName,
                'brandColor' => $brandColor,
                'logoUrl' => $logoUrl,
                'userName' => $notifiable->name ?? '',
                'actionUrl' => $url,
                'expiraMinutos' => $expiraMinutos,
            ]);
    }
}
