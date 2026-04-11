<?php

namespace App\Services\Viabilidade;

use App\Models\Lote;
use App\Models\Cnae;
use App\Models\Zona;
use App\Models\ParametroUrbano;
use Illuminate\Support\Facades\DB;

class ViabilidadeService
{
    /**
     * Realiza a consulta de viabilidade para um Lote e uma lista de códigos CNAE.
     */
    public function analisar(int $loteId, array $codigosCnae)
    {
        // 1. BUSCAR O LOTE JÁ COM A ZONA VINCULADA
        // Usamos o Eloquent 'with' para trazer a zona junto. Simples e direto.
        $lote = Lote::with('zona')->find($loteId);

        if (!$lote) {
            return ['error' => 'Lote não encontrado.'];
        }

        // Verifica se o lote tem o vínculo com a zona
        if (!$lote->zona || empty($lote->zona->sigla)) {
            return ['error' => 'O Lote não possui uma Zona vinculada (zona_id nulo) ou a Zona não tem Sigla.'];
        }

        // Recupera a sigla direto do relacionamento
        $zonaSigla = $lote->zona->sigla;
        $zonaNome = $lote->zona->name;

        // 2. BUSCAR OS DETALHES DOS CNAES NO BANCO
        $cnaes = Cnae::whereIn('codigo', $codigosCnae)->get();

        $resultado = [
            'lote_id' => $lote->id,
            'zona' => [
                'nome' => $zonaNome,
                'sigla' => $zonaSigla
            ],
            'analises' => []
        ];

        // 3. O LOOP DA VIABILIDADE
        foreach ($cnaes as $cnae) {

            // O Model já entrega como array por causa do 'casts'
            $classificacoes = $cnae->classificacoes ?? [];

            // Busca os status dessas classificações na tabela de regras para ESTA zona
            $regras = DB::table('zoneamento_regras')
                ->where('zona_sigla', $zonaSigla) // Usa a sigla recuperada do relacionamento
                ->whereIn('classificacao', $classificacoes)
                ->get();

            // Mapeia o status de cada classificação encontrada
            $statusEncontrados = [];
            foreach ($regras as $r) {
                $statusEncontrados[$r->classificacao] = $r->status;
            }

            // Calcula o Status Final do CNAE
            $statusFinal = 'proibido';
            $detalhes = [];

            foreach ($classificacoes as $cls) {
                $st = $statusEncontrados[$cls] ?? 'proibido';

                $detalhes[] = [
                    'classificacao' => $cls,
                    'status' => $st
                ];

                if ($st === 'permitido') {
                    $statusFinal = 'permitido';
                } elseif ($st === 'permissivel' && $statusFinal !== 'permitido') {
                    $statusFinal = 'permissivel';
                }
            }

            $resultado['analises'][] = [
                'cnae' => $cnae->codigo,
                'descricao' => $cnae->descricao,
                'status_final' => $statusFinal,
                'classificacoes_detalhe' => $detalhes
            ];
        }

        return $resultado;
    }

    /**
     * =========================================================================
     * NOVA FUNÇÃO ISOLADA: PARCELAMENTO DO SOLO (Com Área e Face)
     * =========================================================================
     */
    public function analisarParcelamento(int $loteId, int $qtdLotes)
    {
        $lote = Lote::with('zona')->find($loteId);

        if (!$lote || !$lote->zona || empty($lote->zona->sigla)) {
            return ['error' => 'Lote não encontrado ou sem Zona de Uso vinculada.'];
        }

        // 1. ÁREA: Usa a sua coluna oficial 'area_geo'. (Com Fallback para PostGIS se estiver 0)
        $areaLoteAtual = (float) ($lote->area_geo ?? 0);
        if ($areaLoteAtual <= 0) {
            $geoCalc = DB::table('lotes')
                ->selectRaw("ST_Area(geo::geography) as area_m2")
                ->where('id', $loteId)
                ->first();
            $areaLoteAtual = $geoCalc ? (float) $geoCalc->area_m2 : 0;
        }
        
        // 2. FACE / TESTADA: Lendo diretamente da sua coluna oficial 'main_facade_length'
        $testadaLoteAtual = (float) ($lote->main_facade_length ?? 0);
        
        $zonaSigla = $lote->zona->sigla;

        // 3. Monta a estrutura base
        $resultado = [
            'lote_id' => $lote->id,
            'numero_lote' => $lote->numero_lote ?? 'S/N',
            'zona' => [
                'nome' => $lote->zona->name,
                'sigla' => $zonaSigla,
            ],
            'tipo_consulta' => 'parcelamento',
            'area_base_m2' => round($areaLoteAtual, 2),
            'testada_base_m' => round($testadaLoteAtual, 2),
            'qtd_lotes' => $qtdLotes,
            'status_final' => 'pendente',
            'parecer_tecnico' => []
        ];

        // 4. Busca os Parâmetros da Zona no Backoffice
        $paramUrbano = \App\Models\ParametroUrbano::where('zona_id', $lote->zona_id)->first();

        if (!$paramUrbano) {
            $resultado['status_final'] = 'permissivel';
            $resultado['parecer_tecnico'][] = "Atenção: Os Parâmetros Urbanísticos não foram configurados no sistema para a zona {$zonaSigla}. Análise sujeita à avaliação manual da engenharia.";
            return $resultado;
        }

        // 5. Parâmetros Exigidos pela Zona
        $areaMinimaExigida = (float) $paramUrbano->area_minima;
        $testadaMinimaExigida = (float) $paramUrbano->testada_minima;

        // 6. Frações (A Matemática)
        $areaFracionada = $areaLoteAtual / $qtdLotes;
        $testadaFracionada = $testadaLoteAtual > 0 ? ($testadaLoteAtual / $qtdLotes) : 0;
        
        $resultado['area_fracionada_m2'] = round($areaFracionada, 2);
        $resultado['testada_fracionada_m'] = round($testadaFracionada, 2);

        $erros = [];
        $sucessos = [];

        // Validação 1: ÁREA
        if ($areaMinimaExigida > 0) {
            if ($areaFracionada >= $areaMinimaExigida) {
                $sucessos[] = "✅ ÁREA: Viável. A fração estimada de " . number_format($areaFracionada, 2, ',', '.') . " m² atende ao mínimo exigido (" . number_format($areaMinimaExigida, 2, ',', '.') . " m²).";
            } else {
                $erros[] = "❌ ÁREA: Inviável. A fração estimada de " . number_format($areaFracionada, 2, ',', '.') . " m² é inferior ao mínimo exigido (" . number_format($areaMinimaExigida, 2, ',', '.') . " m²).";
            }
        }

        // Validação 2: TESTADA (FACE)
        if ($testadaMinimaExigida > 0) {
            if ($testadaLoteAtual > 0) {
                if ($testadaFracionada >= $testadaMinimaExigida) {
                    $sucessos[] = "✅ FACE: Viável. A divisão frontal estimada de " . number_format($testadaFracionada, 2, ',', '.') . " m atende ao mínimo exigido (" . number_format($testadaMinimaExigida, 2, ',', '.') . " m).";
                } else {
                    $erros[] = "❌ FACE: Inviável. A divisão frontal estimada de " . number_format($testadaFracionada, 2, ',', '.') . " m é inferior ao mínimo exigido (" . number_format($testadaMinimaExigida, 2, ',', '.') . " m).";
                }
            } else {
                $sucessos[] = "⚠️ FACE: Não analisada automaticamente pois a testada original (main_facade_length) não está preenchida no cadastro do Lote.";
            }
        }

        // 7. Veredito Final
        if (count($erros) > 0) {
            $resultado['status_final'] = 'proibido';
            $resultado['parecer_tecnico'] = array_merge(["O parcelamento solicitado NÃO ATENDE aos parâmetros da Zona {$zonaSigla}:"], $erros, $sucessos);
        } else {
            $resultado['status_final'] = 'permitido';
            $resultado['parecer_tecnico'] = array_merge(["O parcelamento solicitado ATENDE aos parâmetros da Zona {$zonaSigla}:"], $sucessos);
        }

        return $resultado;
    }

    /**
     * =========================================================================
     * NOVA FUNÇÃO ISOLADA: UNIFICAÇÃO DO SOLO
     * =========================================================================
     */
    public function analisarUnificacao(array $lotesIds)
    {
        if (empty($lotesIds)) return ['error' => 'Nenhum lote selecionado.'];

        $loteBase = Lote::with('zona')->find($lotesIds[0]);
        if (!$loteBase || !$loteBase->zona) return ['error' => 'Lote base não encontrado ou sem Zona vinculada.'];

        $zonaSigla = $loteBase->zona->sigla;

        // 1. SOMA MATEMÁTICA VIA POSTGIS (Área M2)
        $somaArea = DB::table('lotes')->selectRaw("SUM(ST_Area(geo::geography)) as area_total")->whereIn('id', $lotesIds)->first();
        $areaTotalResultante = $somaArea ? (float) $somaArea->area_total : 0;

        // 2. SOMA DAS TESTADAS (FACE)
        $testadaTotalResultante = (float) DB::table('lotes')->whereIn('id', $lotesIds)->sum('main_facade_length');

        // Pega os números dos lotes para o relatório
        $numerosLotes = Lote::whereIn('id', $lotesIds)->pluck('numero_lote')->toArray();

        $resultado = [
            'tipo_consulta' => 'unificacao',
            'lote_id' => $loteBase->id,
            'numero_lote' => $loteBase->numero_lote ?? 'S/N', // Lote âncora
            'lotes_envolvidos' => implode(', ', array_filter($numerosLotes)),
            'qtd_lotes' => count($lotesIds),
            'zona' => ['nome' => $loteBase->zona->name, 'sigla' => $zonaSigla],
            'area_resultante_m2' => round($areaTotalResultante, 2),
            'testada_resultante_m' => round($testadaTotalResultante, 2),
            'status_final' => 'pendente',
            'parecer_tecnico' => []
        ];

        // 3. Verifica Limites da Zona (Máximos)
        $paramUrbano = \App\Models\ParametroUrbano::where('zona_id', $loteBase->zona_id)->first();
        if (!$paramUrbano) {
            $resultado['status_final'] = 'permissivel';
            $resultado['parecer_tecnico'][] = "Atenção: Os Parâmetros Urbanísticos não foram configurados para a zona {$zonaSigla}.";
            return $resultado;
        }

        $areaMaximaExigida = (float) $paramUrbano->area_maxima;
        $testadaMaximaExigida = (float) $paramUrbano->testada_maxima;
        $erros = []; $sucessos = [];

        // Validação ÁREA
        if ($areaMaximaExigida > 0) {
            if ($areaTotalResultante <= $areaMaximaExigida) {
                $sucessos[] = "✅ ÁREA: Viável. A soma das áreas (" . number_format($areaTotalResultante, 2, ',', '.') . " m²) não excede o limite máximo permitido (" . number_format($areaMaximaExigida, 2, ',', '.') . " m²).";
            } else {
                $erros[] = "❌ ÁREA: Inviável. A soma das áreas (" . number_format($areaTotalResultante, 2, ',', '.') . " m²) excede o limite máximo permitido para a Zona (" . number_format($areaMaximaExigida, 2, ',', '.') . " m²).";
            }
        }

        // Validação FACE
        if ($testadaMaximaExigida > 0) {
            if ($testadaTotalResultante <= $testadaMaximaExigida) {
                $sucessos[] = "✅ FACE: Viável. A soma das testadas frontais (" . number_format($testadaTotalResultante, 2, ',', '.') . " m) não excede o máximo permitido (" . number_format($testadaMaximaExigida, 2, ',', '.') . " m).";
            } else {
                $erros[] = "❌ FACE: Inviável. A soma das testadas frontais (" . number_format($testadaTotalResultante, 2, ',', '.') . " m) excede o máximo permitido (" . number_format($testadaMaximaExigida, 2, ',', '.') . " m).";
            }
        }

        // Veredito
        if (count($erros) > 0) {
            $resultado['status_final'] = 'proibido';
            $resultado['parecer_tecnico'] = array_merge(["A unificação solicitada NÃO ATENDE aos parâmetros da Zona {$zonaSigla}:"], $erros, $sucessos);
        } else {
            $resultado['status_final'] = 'permitido';
            $resultado['parecer_tecnico'] = array_merge(["A unificação solicitada ATENDE aos parâmetros da Zona {$zonaSigla}:"], $sucessos);
        }

        return $resultado;
    }
}