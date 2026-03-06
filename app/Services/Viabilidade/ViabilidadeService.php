<?php

namespace App\Services\Viabilidade;

use App\Models\Lote;
use App\Models\Cnae;
use App\Models\Zona;
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
}