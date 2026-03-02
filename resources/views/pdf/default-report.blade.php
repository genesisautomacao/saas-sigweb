<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Relatório' }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #333; }
        .table-container { margin: auto; width: 95%; }
        h1 { text-align: center; margin-bottom: 20px; font-size: 24px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #dddddd; text-align: left; padding: 8px; font-size: 12px; }
        thead tr { background-color: #f2f2f2; }
        tbody tr:nth-child(even) { background-color: #f9f9f9; }
        .logo-container { text-align: center; margin-bottom: 20px; }
        .brand-image { max-height: 80px; }
    </style>
</head>
<body>
    <div class="table-container">
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

        <h1>{{ $title ?? 'Relatório' }}</h1>
        
        <table>
            <thead>
                <tr>
                    @foreach($headings as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @if(isset($data) && $data->isNotEmpty())
                    @foreach($data as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="{{ count($headings) }}" style="text-align: center;">Nenhum dado para exibir.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</body>
</html>