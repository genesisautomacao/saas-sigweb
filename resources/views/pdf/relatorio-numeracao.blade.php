<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Numeração Predial</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
        .header h2 { margin: 0 0 5px 0; color: #1e3a8a; text-transform: uppercase; font-size: 18px; }
        .header p { margin: 0; color: #6b7280; font-size: 11px; }
        .map-container { text-align: center; margin-bottom: 20px; }
        .map-img { max-width: 100%; max-height: 400px; border: 2px solid #e5e7eb; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: center; }
        th { background-color: #f3f4f6; color: #374151; font-weight: bold; text-transform: uppercase; font-size: 10px; }
        td { font-size: 11px; }
        .highlight { color: #2563eb; font-weight: bold; background-color: #eff6ff; }
        .bg-gray { background-color: #f9fafb; }
        .logo-container { text-align: center; margin-bottom: 10px; }
        .brand-image { max-height: 60px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
           @php
               // Pega a Tenant ativa de forma nativa no Filament v3
               $tenant = \Filament\Facades\Filament::getTenant();

               // No futuro, quando você adicionar uma coluna 'logo' na sua tabela de tenants, é só ajustar aqui:
               $logoPath = $tenant ? data_get($tenant, 'data.logo') : null;
           @endphp

           @if ($logoPath && file_exists(public_path('storage/' . $logoPath)))
                <img src="{{ public_path('storage/' . $logoPath) }}" alt="Logo" class="brand-image" />
            @else
                <h2 style="color: #666;">{{ $tenant ? $tenant->name : 'SaaS Base' }}</h2>
            @endif
        </div>
        <h2>Relatório de Numeração Predial</h2>
        <p><strong>LOGRADOURO:</strong> {{ mb_strtoupper($rua) }} &nbsp;|&nbsp; <strong>DATA DA PRÉVIA:</strong> {{ $data }}</p>
    </div>

    @if($imagemMapa)
    <div class="map-container">
        <img src="{{ $imagemMapa }}" class="map-img">
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Lote ID (Sistema)</th>
                <th>Distância (Marco Zero)</th>
                <th>Número Atual</th>
                <th class="highlight">Novo Número (Proposto)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dados as $index => $item)
            <tr class="{{ $index % 2 == 0 ? 'bg-gray' : '' }}">
                <td>#{{ $item['lote_id'] }}</td>
                <td>{{ $item['distancia'] }}m</td>
                <td>{{ $item['numero_atual'] }}</td>
                <td class="highlight">{{ $item['novo_numero'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>