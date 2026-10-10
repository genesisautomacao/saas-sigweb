<?php

namespace App\Console\Commands;

use App\Support\Midia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * INF-2 — libera o disco da VPS depois do `midia:migrar-bucket`.
 *
 * Varre as pastas legadas e apaga um arquivo SÓ quando: (1) nenhum registro aponta mais
 * para o caminho local E (2) existe no bucket a cópia que o banco usa, com o MESMO
 * tamanho. Arquivo sem nenhuma referência no banco é listado e mantido (não sabemos de
 * onde veio). Simulação por padrão; `--executar` apaga.
 */
class MidiaLimparLocal extends Command
{
    protected $signature = 'midia:limpar-local
        {--executar : apaga de fato (sem a opção, só simula)}';

    protected $description = 'Apaga do disco local os arquivos já copiados para os buckets do R2';

    /** Pastas legadas na VPS (disk "public"). */
    public const PASTAS_LOCAIS = [
        'processos_anexos', 'documentos', 'unidades_imobiliarias/documentos', 'chamados_fotos',
        'chamados/fotos', 'secoes_logradouro/fotos', 'solicitacoes_fotos', 'os_fotos', 'panoramas',
    ];

    public function handle(): int
    {
        $executar = (bool) $this->option('executar');
        [$emUsoLocal, $copias] = $this->referencias();
        $local = Storage::disk(Midia::DISCO_LOCAL);

        $c = ['emUso' => 0, 'apagaveis' => 0, 'bytes' => 0, 'semCopia' => 0, 'semReferencia' => 0, 'apagados' => 0];
        $semReferencia = [];

        foreach (self::PASTAS_LOCAIS as $pasta) {
            if (! $local->exists($pasta)) {
                continue;
            }
            foreach ($local->allFiles($pasta) as $arquivo) {
                if (isset($emUsoLocal[$arquivo])) {
                    $c['emUso']++;

                    continue;
                }
                if (! isset($copias[$arquivo])) {
                    $c['semReferencia']++;
                    $semReferencia[] = $arquivo;

                    continue;
                }

                $tamanho = $local->size($arquivo);
                $temCopia = false;
                foreach ($copias[$arquivo] as $noBucket) {
                    try {
                        $disco = Storage::disk(Midia::discoBucket($noBucket));
                        if ($disco->exists($noBucket) && $disco->size($noBucket) === $tamanho) {
                            $temCopia = true;
                            break;
                        }
                    } catch (\Throwable) {
                        // sem confirmação = não apaga
                    }
                }
                if (! $temCopia) {
                    $c['semCopia']++;

                    continue;
                }

                $c['apagaveis']++;
                $c['bytes'] += $tamanho;
                if ($executar && $local->delete($arquivo)) {
                    $c['apagados']++;
                }
            }
        }

        $this->info($executar ? '🧹 Limpeza aplicada.' : '🔎 SIMULAÇÃO — nada foi apagado (use --executar).');
        $this->table(['Situação', 'Arquivos'], [
            ['Ainda usados no disco local (não migrados)', $c['emUso']],
            ['Com cópia conferida no bucket — '.($executar ? 'apagados' : 'apagáveis').' ('.number_format($c['bytes'] / 1048576, 1, ',', '.').' MB)', $executar ? $c['apagados'] : $c['apagaveis']],
            ['Banco aponta para o bucket, mas a cópia não confere — mantidos', $c['semCopia']],
            ['Sem nenhuma referência no banco — mantidos', $c['semReferencia']],
        ]);
        foreach (array_slice($semReferencia, 0, 15) as $arquivo) {
            $this->line("  · sem referência: {$arquivo}");
        }

        return self::SUCCESS;
    }

    /**
     * [caminhos locais ainda referenciados, caminho local => [cópias no bucket]].
     * A "cópia" de um caminho do bucket é o caminho sem o slug (360 avulsas: panoramas/{arquivo}).
     */
    private function referencias(): array
    {
        $emUso = [];
        $copias = [];

        $registrar = function (?string $caminho) use (&$emUso, &$copias) {
            if (blank($caminho)) {
                return;
            }
            $caminho = ltrim($caminho, '/');
            if (! Midia::noBucket($caminho)) {
                $emUso[$caminho] = true;

                return;
            }
            $semSlug = substr($caminho, strpos($caminho, '/') + 1);
            $legado = str_starts_with($semSlug, 'panoramicas/avulsas/') ? 'panoramas/'.basename($semSlug) : $semSlug;
            $copias[$legado][] = $caminho;
        };

        $varrerJson = function (mixed $valor) use (&$varrerJson, $registrar) {
            if (is_string($valor) && str_contains($valor, '/')) {
                $registrar($valor);
            } elseif (is_array($valor)) {
                foreach ($valor as $v) {
                    $varrerJson($v);
                }
            }
        };

        foreach (MidiaMigrarBucket::TIPOS as $cfg) {
            if (! Schema::hasTable($cfg['tabela'])) {
                continue;
            }
            $consulta = DB::table($cfg['tabela'])->select($cfg['colunas']);
            if ($cfg['tabela'] === 'pontos_panoramicos') {
                $consulta->where('image_path', 'like', 'panoramas/%')->orWhere('image_path', 'like', '%/panoramicas/avulsas/%');
            }
            foreach ($consulta->cursor() as $linha) {
                foreach ($cfg['colunas'] as $coluna) {
                    $cfg['json'] ? $varrerJson(json_decode($linha->{$coluna} ?? 'null', true)) : $registrar($linha->{$coluna});
                }
            }
        }

        foreach (DB::table('processo_anexos')->select('caminho_arquivo')->cursor() as $a) {
            $registrar($a->caminho_arquivo);
        }
        foreach (DB::table('processos_digitais')->whereRaw("dados_formulario::text like '%processos_anexos/%'")->select('dados_formulario')->cursor() as $p) {
            $varrerJson(json_decode($p->dados_formulario ?? 'null', true));
        }
        if (Schema::hasTable('processo_respostas')) {
            foreach (DB::table('processo_respostas')->whereRaw("dados::text like '%processos_anexos/%'")->select('dados')->cursor() as $r) {
                $varrerJson(json_decode($r->dados ?? 'null', true));
            }
        }

        return [$emUso, $copias];
    }
}
