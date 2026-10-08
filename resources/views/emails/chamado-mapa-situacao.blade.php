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
        .badge { display: inline-block; padding: 4px 12px; border-radius: 999px; color: #ffffff; font-weight: bold; font-size: 14px; background-color: {{ $corSituacao }}; }
        .resposta-box { background-color: #f0f9ff; border-left: 4px solid {{ $corSituacao }}; padding: 15px 20px; margin: 20px 0; border-radius: 0 8px 8px 0; }
        .resposta-box .titulo { font-weight: bold; margin: 0 0 6px 0; }
        .footer { margin-top: 8px; font-size: 12px; color: #a1a1aa; text-align: center; border-top: 1px solid #e4e4e7; padding: 20px 40px; }
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
            <p class="heading">Atualização do seu chamado</p>

            <p>Olá, <strong>{{ $nome }}</strong>!</p>
            <p>O chamado que você abriu no mapa da prefeitura teve a situação atualizada:</p>

            <p style="text-align: center; margin: 24px 0;"><span class="badge">{{ $situacao }}</span></p>

            <div class="details-box">
                <p><strong>Protocolo:</strong> {{ $protocolo }}</p>
                <p><strong>Assunto:</strong> {{ $assunto }}</p>
                <p><strong>Título:</strong> {{ $titulo }}</p>
                @if ($abertoEm)
                    <p><strong>Aberto em:</strong> {{ $abertoEm }}</p>
                @endif
            </div>

            @if (filled($resposta))
                <div class="resposta-box">
                    <p class="titulo">Resposta da prefeitura:</p>
                    <p>{!! nl2br(e($resposta)) !!}</p>
                </div>
            @endif

            <p style="margin-top: 28px;">
                Guarde o número do protocolo para acompanhar o seu chamado.<br><br>
                Atenciosamente,<br>
                <strong>{{ $tenantName }}</strong>
            </p>
        </div>

        <div class="footer">
            <p>Esta é uma mensagem automática — por favor, não responda a este e-mail.</p>
            <p>&copy; {{ date('Y') }} {{ $tenantName }}. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
