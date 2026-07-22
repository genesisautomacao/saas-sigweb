<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f5; margin: 0; padding: 40px 20px; color: #3f3f46; }
        .container { background-color: #ffffff; border-radius: 8px; max-width: 600px; margin: 0 auto; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .header { text-align: center; padding: 28px 40px; background-color: {{ $brandColor }}; }
        .header img { max-height: 56px; max-width: 220px; }
        .header .title { color: #ffffff; font-size: 22px; font-weight: bold; margin: 0; }
        .content { padding: 36px 40px; }
        .heading { color: #18181b; font-size: 20px; font-weight: bold; margin: 0 0 16px 0; }
        .button-container { text-align: center; margin: 32px 0 8px 0; }
        .button { background-color: {{ $brandColor }}; color: #ffffff !important; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; }
        .muted { font-size: 13px; color: #71717a; }
        .footer { margin-top: 8px; font-size: 12px; color: #a1a1aa; text-align: center; border-top: 1px solid #e4e4e7; padding: 20px 40px; }
        .trouble-link { color: {{ $brandColor }}; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $tenantName }}">
            @else
                <h1 class="title">{{ $tenantName }}</h1>
            @endif
        </div>

        <div class="content">
            <p class="heading">Redefinição de senha</p>

            <p>Olá{{ $userName ? ', ' : '' }}<strong>{{ $userName }}</strong>!</p>
            <p>Recebemos um pedido para redefinir a senha da sua conta no Portal do Cidadão. Clique no botão abaixo para criar uma nova senha:</p>

            <div class="button-container">
                <a href="{{ $actionUrl }}" class="button">Redefinir senha</a>
            </div>

            <p class="muted" style="margin-top: 24px;">Este link expira em {{ $expiraMinutos }} minutos.</p>
            <p class="muted">Se você não solicitou a redefinição, ignore este e-mail — nada será alterado na sua conta.</p>

            <p style="margin-top: 28px;">
                Atenciosamente,<br>
                <strong>{{ $tenantName }}</strong>
            </p>
        </div>

        <div class="footer">
            <p>Se você estiver tendo problemas para clicar no botão, copie e cole a URL abaixo no seu navegador:</p>
            <p><a href="{{ $actionUrl }}" class="trouble-link">{{ $actionUrl }}</a></p>
            <p>Esta é uma mensagem automática — por favor, não responda a este e-mail.</p>
            <p>&copy; {{ date('Y') }} {{ $tenantName }}. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
