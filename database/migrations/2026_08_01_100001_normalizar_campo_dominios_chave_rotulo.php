<?php

use App\Services\Coleta\CampoDominioService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * N1 (PoC Tangará) — separar CHAVE e RÓTULO nos campos padrão white-label.
 *
 * Antes: `campo_dominios.opcoes` guardava uma lista solta de rótulos e o Select gravava
 * o RÓTULO na coluna. Resultado real encontrado na base:
 *   lotes.situacao_quadra -> 'esquina' E 'Esquina'   (mesmo conceito, dois valores)
 *   lotes.ocupacao        -> 'Em Contrução'          (valor inventado, com typo)
 *
 * Isso faria o mapa temático por valores únicos (T1.10) pintar duas classes para a mesma
 * coisa e as estatísticas (T1.12) contarem separado.
 *
 * Depois: `opcoes` guarda `chave do sistema => rótulo do município`. As chaves são
 * imutáveis (decisão D6) e as colunas voltam a guardar chave.
 *
 * Esta migration:
 *   1. converte as configurações existentes para o formato de mapa;
 *   2. normaliza os valores já gravados nas colunas quando dá para reconhecê-los;
 *   3. NÃO apaga valor órfão (ex.: 'Em Contrução') — reporta para o município decidir.
 */
return new class extends Migration
{
    /** [tabela, entidade, campo] das colunas governadas por lista de valores. */
    private const COLUNAS = [
        ['lotes', 'lote', 'ocupacao'],
        ['lotes', 'lote', 'situacao_quadra'],
        ['edificacoes', 'edificacao', 'tipo'],
        ['edificacoes', 'edificacao', 'tp_construcao'],
        ['edificacoes', 'edificacao', 'estado_conservacao'],
    ];

    public function up(): void
    {
        $relatorio = [];

        // ── 1. campo_dominios: lista solta -> mapa chave => rótulo ──────────────
        if (Schema::hasTable('campo_dominios')) {
            foreach (DB::table('campo_dominios')->whereNotNull('opcoes')->get() as $linha) {
                $opcoes = json_decode($linha->opcoes, true);

                if (! is_array($opcoes) || $opcoes === []) {
                    continue;
                }

                // Já está no formato novo (chaves não-numéricas)? Não mexe.
                if (! array_is_list($opcoes)) {
                    continue;
                }

                $padrao = CampoDominioService::PADROES[$linha->entidade][$linha->campo]['opcoes'] ?? [];
                $mapa = [];
                $descartados = [];

                foreach ($opcoes as $rotuloAntigo) {
                    $chave = $this->casarChave((string) $rotuloAntigo, $padrao);

                    if ($chave !== null) {
                        $mapa[$chave] = (string) $rotuloAntigo;
                    } else {
                        $descartados[] = (string) $rotuloAntigo;
                    }
                }

                DB::table('campo_dominios')->where('id', $linha->id)->update([
                    'opcoes' => $mapa === [] ? null : json_encode($mapa, JSON_UNESCAPED_UNICODE),
                ]);

                if ($descartados !== []) {
                    $relatorio[] = sprintf(
                        'tenant %d · %s.%s — valores sem equivalente no sistema, removidos da lista: %s',
                        $linha->tenant_id,
                        $linha->entidade,
                        $linha->campo,
                        implode(', ', $descartados)
                    );
                }
            }
        }

        // ── 2. colunas: rótulo gravado -> chave do sistema ──────────────────────
        foreach (self::COLUNAS as [$tabela, $entidade, $campo]) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, $campo)) {
                continue;
            }

            $padrao = CampoDominioService::PADROES[$entidade][$campo]['opcoes'] ?? [];

            if ($padrao === []) {
                continue;
            }

            $distintos = DB::table($tabela)
                ->select($campo, 'tenant_id')
                ->whereNotNull($campo)
                ->distinct()
                ->get();

            foreach ($distintos as $registro) {
                $valor = (string) $registro->{$campo};

                if ($valor === '' || array_key_exists($valor, $padrao)) {
                    continue; // já é chave válida
                }

                $chave = $this->casarChave($valor, $padrao);

                if ($chave === null) {
                    $relatorio[] = sprintf(
                        'tenant %d · %s.%s — valor órfão MANTIDO: "%s" (crie um Campo Customizado se ainda for necessário)',
                        $registro->tenant_id,
                        $tabela,
                        $campo,
                        $valor
                    );

                    continue;
                }

                $afetados = DB::table($tabela)
                    ->where('tenant_id', $registro->tenant_id)
                    ->where($campo, $valor)
                    ->update([$campo => $chave]);

                $relatorio[] = sprintf(
                    'tenant %d · %s.%s — "%s" -> "%s" (%d registro(s))',
                    $registro->tenant_id,
                    $tabela,
                    $campo,
                    $valor,
                    $chave,
                    $afetados
                );
            }
        }

        if ($relatorio !== []) {
            echo PHP_EOL . '  ── Normalização chave/rótulo ──' . PHP_EOL;
            foreach ($relatorio as $linha) {
                echo '   • ' . $linha . PHP_EOL;
            }
            echo PHP_EOL;
        }
    }

    /**
     * Acha a chave do sistema cujo rótulo (ou a própria chave) equivale ao texto,
     * ignorando caixa e acento. Null = sem equivalente.
     */
    private function casarChave(string $texto, array $padrao): ?string
    {
        $alvo = CampoDominioService::comparavel($texto);

        foreach ($padrao as $chave => $rotulo) {
            if (CampoDominioService::comparavel((string) $chave) === $alvo
                || CampoDominioService::comparavel((string) $rotulo) === $alvo) {
                return (string) $chave;
            }
        }

        return null;
    }

    public function down(): void
    {
        // Normalização de dado não é reversível: o formato antigo (rótulo como valor)
        // era justamente o defeito. Reverter recriaria a colisão `esquina`/`Esquina`.
    }
};
