<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Boletim de Cadastro Imobiliário (BIC) - Lote</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #333; margin: 0; padding: 0; }
        .header { border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; margin-bottom: 15px; }
        .header table { width: 100%; border: none; }
        .header td { border: none; padding: 0; vertical-align: middle; }
        .header-title { font-size: 18px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; }
        .header-sub { font-size: 12px; color: #555; text-transform: uppercase; }
        .section-title { background-color: #f3f4f6; color: #1f2937; font-weight: bold; padding: 5px; border: 1px solid #d1d5db; margin-top: 15px; font-size: 11px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        td { border: 1px solid #d1d5db; padding: 6px; vertical-align: top; }
        .label { display: block; font-size: 9px; color: #6b7280; text-transform: uppercase; margin-bottom: 2px; }
        .value { display: block; font-size: 12px; font-weight: bold; color: #111827; }
        .map-container { text-align: center; margin-top: 10px; border: 1px solid #d1d5db; padding: 5px; background-color: #f9fafb; }
        .map-img { max-width: 100%; max-height: 300px; border-radius: 4px; border: 1px solid #ccc; }
        .footer { text-align: center; font-size: 9px; color: #9ca3af; margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 10px; }
        .highlight { background-color: #eff6ff; }
        .alert-red { background-color: #fef2f2; border-color: #f87171; }
        .logo-container { text-align: center; margin-bottom: 10px; }
        .brand-image { max-height: 60px; }
        
        /* 🟢 A MÁGICA DA QUEBRA DE PÁGINA */
        .page-break { page-break-after: always; }
    </style>
</head>

<body>

    @foreach($imoveis as $imovel)
        @php
            // Extrai os dados específicos de cada imóvel do laço
            $dadosJson = $imovel->dados_tributarios ?? [];
        @endphp

        <div class="header">
            <table>
                <tr>
                    <td width="100%" style="text-align: center;">
                        <div class="logo-container">
                            @php
                                $logoPath = $tenant ? data_get($tenant, 'data.logo') : null;
                            @endphp

                            @if ($logoPath && file_exists(public_path('storage/' . $logoPath)))
                                <img src="{{ public_path('storage/' . $logoPath) }}" alt="Logo" class="brand-image" />
                            @else
                                <h2 style="color: #666;">{{ $tenant ? $tenant->name : 'SaaS Base' }}</h2>
                            @endif
                        </div>
                        <div class="header-title">Boletim de Cadastro Imobiliário (BCI)</div>
                        <div class="header-sub">Prefeitura Municipal de {{ $tenant->name ?? 'Santa Cecília - SC' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section-title">1. IDENTIFICAÇÃO DO IMÓVEL</div>
        <table>
            <tr>
                <td width="33%">
                    <span class="label">Inscrição Imobiliária (BIC)</span>
                    <span class="value">{{ $dadosJson['inscricao_imobiliaria'] ?? $imovel->inscricao_imobiliaria ?? 'Não Informada' }}</span>
                </td>
                <td width="33%">
                    <span class="label">Código Tributário</span>
                    <span class="value">{{ $dadosJson['codigo_imovel_tributario'] ?? $imovel->codigo_imovel_tributario ?? 'Não Informado' }}</span>
                </td>
                <td width="34%">
                    <span class="label">Status de Integração</span>
                    <span class="value" style="color: {{ empty($dadosJson) ? '#b91c1c' : '#15803d' }}">
                        {{ empty($dadosJson) ? 'Dados Isolados (Sem API)' : 'Sincronizado com o Fiscom' }}
                    </span>
                </td>
            </tr>
        </table>

        <div class="section-title">2. DADOS DO CONTRIBUINTE</div>
        <table>
            <tr>
                <td width="70%">
                    <span class="label">Nome do Proprietário / Contribuinte</span>
                    <span class="value">{{ $dadosJson['proprietario_name'] ?? $imovel->proprietario->name ?? 'Proprietário Não Informado' }}</span>
                </td>
                <td width="30%">
                    <span class="label">CPF / CNPJ</span>
                    <span class="value">{{ $dadosJson['cpf_cnpj_contribuinte'] ?? 'Não Informado no Cadastro' }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Endereço de Correspondência</span>
                    @php
                        $endCorre = trim(($dadosJson['tipo_logradouro_endereco_contribuinte'] ?? '') . ' ' . ($dadosJson['logradouro_endereco_contribuinte'] ?? 'Não Informado'));
                        $numCorre = $dadosJson['numero_endereco_contribuinte'] ?? 'S/N';
                        $bairroCorre = $dadosJson['bairro_endereco_contribuinte'] ?? '';
                        $munCorre = $dadosJson['municipio_endereco_contribuinte'] ?? '';
                        $ufCorre = $dadosJson['uf_endereco_contribuinte'] ?? '';
                        $cepCorre = $dadosJson['cep_endereco_contribuinte'] ?? '';
                    @endphp
                    <span class="value">{{ $endCorre }}, {{ $numCorre }} - {{ $bairroCorre }} - {{ $munCorre }}/{{ $ufCorre }} (CEP: {{ $cepCorre }})</span>
                </td>
            </tr>
        </table>

        <div class="section-title">3. LOCALIZAÇÃO DO IMÓVEL</div>
        <table>
            <tr>
                <td width="80%">
                    <span class="label">Logradouro</span>
                    <span class="value">{{ trim(($dadosJson['tipo_logradouro'] ?? '') . ' ' . ($dadosJson['logradouro'] ?? 'Logradouro Não Informado')) }}</span>
                </td>
                <td width="20%">
                    <span class="label">Número</span>
                    <span class="value">{{ $dadosJson['numero_logradouro'] ?? 'S/N' }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Vínculo Territorial Geográfico (SIGWEB)</span>
                    <span class="value">
                        Quadra: {{ $imovel->lote->quadra->name ?? 'Não Vinculada' }} | Lote (Terreno): {{ $imovel->lote->numero_lote ?? 'Não Vinculado' }}
                    </span>
                </td>
            </tr>
        </table>

        @if($mapImageBase64)
            <div class="section-title">4. CROQUI DE LOCALIZAÇÃO GEORREFERENCIADA</div>
            <div class="map-container">
                <img src="{{ $mapImageBase64 }}" class="map-img" alt="Croqui Cartográfico">
            </div>
        @endif

        <div class="section-title">5. CARACTERÍSTICAS FÍSICAS</div>
        <table>
            <tr>
                <td width="25%">
                    <span class="label">Área do Terreno (m²)</span>
                    <span class="value">{{ number_format((float) ($dadosJson['area_geo'] ?? 0), 2, ',', '.') }}</span>
                </td>
                <td width="25%">
                    <span class="label">Testada Principal (m)</span>
                    <span class="value">{{ number_format((float) ($dadosJson['testada'] ?? 0), 2, ',', '.') }}</span>
                </td>
                <td width="25%">
                    <span class="label">Área Edificada (m²)</span>
                    <span class="value">{{ number_format((float) ($dadosJson['area_total_edificacao'] ?? 0), 2, ',', '.') }}</span>
                </td>
                <td width="25%">
                    <span class="label">Tipo de Construção</span>
                    <span class="value">{{ $dadosJson['tipo_construcao'] ?? 'Não Avaliado' }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <span class="label">Classificação do Imóvel</span>
                    <span class="value">{{ $dadosJson['descricao_classificacao'] ?? 'Sem Classificação' }} (Cód: {{ $dadosJson['codigo_classificacao'] ?? '-' }})</span>
                </td>
            </tr>
        </table>

        <div class="section-title">6. AVALIAÇÃO E TRIBUTAÇÃO (PGV / IPTU)</div>
        <table>
            <tr>
                <td width="50%">
                    <span class="label">Valor Venal do Terreno</span>
                    <span class="value">R$ {{ number_format((float) ($dadosJson['valor_venal_lote'] ?? 0), 2, ',', '.') }}</span>
                    <span style="font-size: 8px; color: #666;">(Ref: R$ {{ number_format((float) ($dadosJson['valor_metro_terreno'] ?? 0), 2, ',', '.') }}/m²)</span>
                </td>
                <td width="50%">
                    <span class="label">Valor Venal da Edificação</span>
                    <span class="value">R$ {{ number_format((float) ($dadosJson['valor_venal_edificacao'] ?? 0), 2, ',', '.') }}</span>
                    <span style="font-size: 8px; color: #666;">(Ref: R$ {{ number_format((float) ($dadosJson['valor_metro_edificacao'] ?? 0), 2, ',', '.') }}/m²)</span>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="highlight" style="text-align: center;">
                    <span class="label">VALOR VENAL TOTAL DO IMÓVEL (BASE DE CÁLCULO)</span>
                    @php
                        $valorTotal = (float) ($dadosJson['valor_venal_lote'] ?? 0) + (float) ($dadosJson['valor_venal_edificacao'] ?? 0);
                    @endphp
                    <span class="value" style="font-size: 16px; color: #1e3a8a;">R$ {{ number_format($valorTotal, 2, ',', '.') }}</span>
                </td>
            </tr>
        </table>

        <table>
            <tr>
                <td width="33%">
                    <span class="label">Imposto Territorial Lançado</span>
                    <span class="value">R$ {{ number_format((float) ($dadosJson['valor_imposto_territorial'] ?? 0), 2, ',', '.') }}</span>
                </td>
                <td width="33%">
                    <span class="label">Imposto Predial Lançado</span>
                    <span class="value">R$ {{ number_format((float) ($dadosJson['valor_imposto_predial'] ?? 0), 2, ',', '.') }}</span>
                </td>
                <td width="34%" class="highlight">
                    <span class="label">TRIBUTAÇÃO TOTAL (IPTU)</span>
                    <span class="value" style="color: #b91c1c;">R$ {{ number_format((float) ($dadosJson['valor_total_imposto'] ?? 0), 2, ',', '.') }}</span>
                </td>
            </tr>
            @if(!empty($dadosJson['descricao_isento']) && $dadosJson['descricao_isento'] !== 'Não Isento')
                <tr>
                    <td colspan="3" class="alert-red">
                        <span class="label" style="color: #b91c1c;">Aviso de Isenção / Imunidade</span>
                        <span class="value" style="color: #b91c1c;">{{ $dadosJson['descricao_isento'] }} (Cód: {{ $dadosJson['codigo_isento'] ?? '-' }})</span>
                    </td>
                </tr>
            @endif
        </table>

        <div class="footer">
            Gerado pelo Sistema Cartográfico Multifinalitário (SIGWEB)<br>
            Documento Emitido em: {{ $dataHora }} | Chave de Autenticação Cartográfica: {{ \Illuminate\Support\Str::uuid() }}
        </div>

        {{-- Se não for a última página do laço, quebra a página! --}}
        @if(!$loop->last)
            <div class="page-break"></div>
        @endif

    @endforeach

</body>
</html>