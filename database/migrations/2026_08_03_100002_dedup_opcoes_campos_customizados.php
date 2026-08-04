<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PoC Tangará — remove DUPLICATAS de caixa/formato das opções dos campos customizados
 * e normaliza os valores gravados para o rótulo canônico.
 *
 * Origem: a migração que moveu as colunas da edificação/seção/meio-fio para
 * dados_customizados anexou os valores antigos às opções do kit para não deixá-los
 * órfãos. Certo para vocabulário legítimo do fornecedor ("Alvenaria (0)"), errado
 * para variantes do MESMO conceito: o select mostrava "Esquina" E "esquina",
 * "Concreto" E "concreto", "Asfalto" E "asfalto"...
 *
 * Regras:
 *  - duplicata = mesma forma comparável (sem caixa/acento; underscore vira espaço)
 *    de uma opção anterior → REMOVIDA da lista e os valores gravados são REMAPEADOS
 *    para a opção canônica (a primeira da lista, que é o rótulo do kit);
 *  - mapa legado explícito para chaves multi-palavra que a forma comparável não
 *    alcança (meio_quadra → "Meio de Quadra");
 *  - valor distinto de verdade ("Outros" vs "Outro", "Mau", "Alvenaria (0)") NÃO é
 *    tocado — decidir fusão de conceitos é do município, não desta migração.
 */
return new class extends Migration
{
    /** Chave legada => rótulo canônico, por slug (o comparável não alcança). */
    private const MAPA_LEGADO = [
        'situacao_quadra' => ['meio_quadra' => 'Meio de Quadra'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('campos_customizados')) {
            return;
        }

        $comparavel = fn (string $t): string => mb_strtolower(trim(\Illuminate\Support\Str::ascii(str_replace('_', ' ', $t))));

        $campos = DB::table('campos_customizados')
            ->whereIn('tipo', ['selecao', 'multipla'])
            ->whereNull('deleted_at')
            ->whereNotNull('opcoes')
            ->get();

        foreach ($campos as $campo) {
            $opcoes = json_decode($campo->opcoes, true);

            if (! is_array($opcoes) || count($opcoes) < 2) {
                continue;
            }

            $tabela = \App\Services\Coleta\CampoCustomizadoService::ENTIDADE_TABELA[$campo->entidade] ?? null;

            $canonicas = [];   // assinatura comparável => rótulo canônico (1ª ocorrência)
            $limpas = [];
            $remap = [];       // variante duplicada => canônica

            foreach ($opcoes as $opcao) {
                $opcao = (string) $opcao;
                $alvoLegado = self::MAPA_LEGADO[$campo->slug][$opcao] ?? null;

                if ($alvoLegado !== null && in_array($alvoLegado, $limpas, true)) {
                    $remap[$opcao] = $alvoLegado;
                    continue;
                }

                $assinatura = $comparavel($opcao);

                if (isset($canonicas[$assinatura])) {
                    $remap[$opcao] = $canonicas[$assinatura];
                    continue;
                }

                $canonicas[$assinatura] = $opcao;
                $limpas[] = $opcao;
            }

            if ($remap === []) {
                continue;
            }

            // Remapeia os VALORES gravados na entidade (escalar; os campos do kit são seleção única)
            $totalRemapeado = 0;

            if ($tabela && Schema::hasTable($tabela) && Schema::hasColumn($tabela, 'dados_customizados')) {
                foreach ($remap as $de => $para) {
                    $totalRemapeado += DB::table($tabela)
                        ->where('tenant_id', $campo->tenant_id)
                        ->whereRaw("dados_customizados->>'{$campo->slug}' = ?", [$de])
                        ->update([
                            'dados_customizados' => DB::raw(
                                "jsonb_set(dados_customizados, '{{$campo->slug}}', to_jsonb(".DB::getPdo()->quote($para)."::text))"
                            ),
                        ]);
                }
            }

            DB::table('campos_customizados')->where('id', $campo->id)->update([
                'opcoes' => json_encode($limpas, JSON_UNESCAPED_UNICODE),
            ]);

            echo "   • tenant {$campo->tenant_id} · {$campo->entidade}.{$campo->slug} — "
                .count($remap)." duplicata(s) fundida(s) ("
                .implode(', ', array_map(fn ($d, $p) => "\"{$d}\"→\"{$p}\"", array_keys($remap), $remap))
                ."), {$totalRemapeado} registro(s) remapeado(s)".PHP_EOL;
        }
    }

    public function down(): void
    {
        // Fusão de duplicatas não é reversível (o formato antigo era o defeito).
    }
};
