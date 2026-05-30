<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa Público — Selecione sua Prefeitura</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh;
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: #111827;
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
        }
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
            padding: 36px 32px;
            max-width: 480px;
            width: 100%;
        }
        h1 { font-size: 22px; margin: 0 0 8px; color: #1f2937; }
        .sub { color: #6b7280; margin: 0 0 28px; font-size: 14px; line-height: 1.5; }
        label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; }
        select {
            width: 100%; padding: 12px 14px;
            border: 1px solid #d1d5db; border-radius: 10px;
            font-size: 15px; background: white; color: #111827;
        }
        select:focus { outline: 2px solid #2563eb; outline-offset: 1px; border-color: #2563eb; }
        button[type=submit] {
            margin-top: 20px; width: 100%; padding: 14px;
            background: #2563eb; color: white; border: none; border-radius: 12px;
            font-weight: 700; font-size: 14px; cursor: pointer;
            transition: background .15s;
        }
        button[type=submit]:hover { background: #1d4ed8; }
        .footer { text-align: center; margin-top: 24px; font-size: 13px; color: #6b7280; }
        .footer a { color: #2563eb; text-decoration: none; font-weight: 600; }
        .footer a:hover { text-decoration: underline; }
        .empty { padding: 18px; background: #fef3c7; color: #92400e; border-radius: 10px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Acessar Mapa Público</h1>
        <p class="sub">Selecione a sua prefeitura para visualizar o mapa interativo sem precisar fazer login. Você poderá navegar, buscar imóveis, consultar zoneamento, viabilidade e imprimir.</p>

        @if($tenants->isEmpty())
            <div class="empty">Nenhuma prefeitura disponível no momento.</div>
        @else
            <form method="GET" action="/cidadao/mapa-publico">
                <label for="t">Prefeitura</label>
                <select id="t" name="t" required>
                    <option value="">— Escolha a sua cidade —</option>
                    @foreach($tenants as $t)
                        <option value="{{ $t->slug }}">{{ $t->name }}</option>
                    @endforeach
                </select>
                <button type="submit">Abrir mapa</button>
            </form>
        @endif

        <div class="footer">
            Já tem cadastro? <a href="/cidadao/login">Entrar no portal</a>
        </div>
    </div>
</body>
</html>
