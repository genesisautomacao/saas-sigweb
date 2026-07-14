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
        .details-box { background-color: #f8fafc; border-left: 4px solid {{ $brandColor }}; padding: 15px 20px; margin: 20px 0; border-radius: 0 8px 8px 0; }
        .details-box p { margin: 0 0 8px 0; }
        .details-box p:last-child { margin-bottom: 0; }
        .reject-box { background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 15px 20px; margin: 20px 0; border-radius: 0 8px 8px 0; color: #991b1b; }
        .reject-box .reject-title { font-weight: bold; margin: 0 0 6px 0; }
        .button-container { text-align: center; margin: 32px 0 8px 0; }
        .button { background-color: {{ $brandColor }}; color: #ffffff !important; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; }
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
            <p class="heading">{{ $heading }}</p>

            <p>Olá, <strong>{{ $citizenName }}</strong>!</p>
            <p>{{ $corpo }}</p>

            <div class="details-box">
                <p><strong>Processo:</strong> {{ $processCode }}</p>
                <p><strong>Fluxo:</strong> {{ $fluxoNome }}</p>
                @if (!empty($etapaNome))
                    <p><strong>Etapa atual:</strong> {{ $etapaNome }}</p>
                @endif
            </div>

            @if (!empty($rejectReason))
                <div class="reject-box">
                    <p class="reject-title">Motivo da devolução:</p>
                    <p>{{ $rejectReason }}</p>
                </div>
            @endif

            @if (!empty($actionUrl))
                <div class="button-container">
                    <a href="{{ $actionUrl }}" class="button">{{ $actionLabel }}</a>
                </div>
            @endif

            <p style="margin-top: 28px;">
                Atenciosamente,<br>
                <strong>{{ $tenantName }}</strong>
            </p>
        </div>

        <div class="footer">
            @if (!empty($actionUrl))
                <p>Se você estiver tendo problemas para clicar no botão, copie e cole a URL abaixo no seu navegador:</p>
                <p><a href="{{ $actionUrl }}" class="trouble-link">{{ $actionUrl }}</a></p>
            @endif
            <p>Esta é uma mensagem automática — por favor, não responda a este e-mail.</p>
            <p>&copy; {{ date('Y') }} {{ $tenantName }}. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
