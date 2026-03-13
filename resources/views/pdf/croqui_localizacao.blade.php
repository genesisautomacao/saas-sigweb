<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Croqui de Localização</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #333; margin: 0; padding: 0; }
        
        .header { border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; margin-bottom: 15px; }
        .header table { width: 100%; border: none; }
        .header td { border: none; padding: 0; vertical-align: middle; }
        .header-title { font-size: 18px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; }
        .header-sub { font-size: 12px; color: #555; text-transform: uppercase; }
        
        .section-title { background-color: #f3f4f6; color: #1f2937; font-weight: bold; padding: 6px; border: 1px solid #d1d5db; margin-top: 15px; margin-bottom: 5px; font-size: 12px; text-transform: uppercase; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        td { border: 1px solid #d1d5db; padding: 6px; vertical-align: top; }
        
        .label { display: block; font-size: 9px; color: #6b7280; text-transform: uppercase; margin-bottom: 2px; }
        .value { display: block; font-size: 13px; font-weight: bold; color: #111827; }
        
        /* Destaque para o Mapa */
        .map-container { text-align: center; border: 2px solid #1e3a8a; padding: 5px; background-color: #f9fafb; margin-top: 10px; height: 500px; overflow: hidden; }
        .map-img { width: 100%; height: 100%; object-fit: cover; border-radius: 2px; }
        
        .footer { text-align: center; font-size: 9px; color: #9ca3af; margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 10px; position: absolute; bottom: -20px; width: 100%; }
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                <td width="100%" style="text-align: right;">
                    <div class="header-title">Croqui de Localização Cartográfica</div>
                    <div class="header-sub">Prefeitura Municipal de {{ $tenant->name ?? 'Município' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Identificação do Lote (Terreno)</div>
    <table>
        <tr>
            <td width="30%">
                <span class="label">Lote Cadastral Nº</span>
                <span class="value">{{ $lote->numero_lote ?? 'S/N' }}</span>
            </td>
            <td width="30%">
                <span class="label">Quadra Nº</span>
                <span class="value">{{ $lote->quadra->name ?? 'Não Vinculada' }}</span>
            </td>
            <td width="40%">
                <span class="label">Bairro / Setor</span>
                <span class="value">{{ $lote->quadra->bairro->name ?? 'Não Vinculado' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Área Poligonal Calculada</span>
                <span class="value">{{ number_format($lote->area_geo ?? 0, 2, ',', '.') }} m²</span>
            </td>
            <td>
                <span class="label">Testada Principal</span>
                <span class="value">{{ $lote->main_facade_length ? number_format($lote->main_facade_length, 2, ',', '.') . ' m' : 'Não informada' }}</span>
            </td>
            <td>
                <span class="label">Zoneamento (Uso do Solo)</span>
                <span class="value">{{ $lote->zona->sigla ?? 'Não Classificado' }}</span>
            </td>
        </tr>
    </table>

    <div class="section-title">Mapa de Situação / Geometria</div>
    <div class="map-container">
        <img src="{{ $mapImageBase64 }}" class="map-img" alt="Croqui">
    </div>

    <div style="margin-top: 10px; font-size: 10px; color: #555; text-align: justify;">
        <strong>Nota Técnica:</strong> Este documento possui caráter puramente referencial e informativo, extraído da base de dados do Sistema de Informação Geográfica (SIGWEB). Para fins de confrontação legal e registro em cartório, exige-se o Memorial Descritivo assinado por profissional técnico habilitado (ART/RRT).
    </div>

    <div class="footer">
        Gerado pelo Sistema Cartográfico Multifinalitário (SIGWEB)<br>
        Documento Emitido em: {{ $dataHora }} | ID do Lote no Banco de Dados: #{{ $lote->id }}
    </div>

</body>
</html>