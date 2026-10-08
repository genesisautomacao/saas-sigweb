<?php

namespace App\Console\Commands;

use App\Support\FotosLote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * R79-1 — libera o disco da VPS depois do `fotos:migrar-bucket`.
 *
 * Varre as pastas de fotos de lote do disco local (`lotes_fotos/` e a antiga
 * `lotes/fotos-frontais/`, ou as informadas em --pasta) e apaga um arquivo SOMENTE
 * quando: (1) nenhum lote ainda aponta para esse caminho local, e (2) algum lote aponta
 * para `{slug}/lotes_fotos/{mesmo nome}` no bucket e a cópia lá tem o MESMO tamanho.
 * Simulação por padrão; `--executar` apaga. Arquivo sem cópia no bucket não é tocado.
 */
class FotosLimparLocal extends Command
{
    protected $signature = 'fotos:limpar-local
        {--pasta=* : pastas do disco public a varrer (padrão: lotes_fotos e lotes/fotos-frontais)}
        {--executar : apaga de fato (sem a opção, só simula)}';

    protected $description = 'Apaga do disco da VPS as fotos de lote já confirmadas no bucket público';

    public function handle(): int
    {
        if (! FotosLote::bucketAtivo()) {
            $this->error('Bucket de fotos sem credenciais: cadastre "Cloudflare R2 Fotos" em /admin → Configurações de APIs.');

            return self::FAILURE;
        }

        $executar = (bool) $this->option('executar');
        $pastas = $this->option('pasta') ?: [FotosLote::PASTA, 'lotes/fotos-frontais'];
        $local = Storage::disk(FotosLote::DISCO_LOCAL);
        $bucket = Storage::disk(FotosLote::DISCO_BUCKET);

        $this->info($executar ? '🧹 APAGANDO cópias locais já confirmadas no bucket…' : '🔎 SIMULAÇÃO — nada será apagado (use --executar).');

        // Caminhos gravados nos lotes (inclusive lixeira): os locais ainda em uso e,
        // por nome de arquivo, onde está a cópia no bucket.
        $emUsoLocal = [];
        $noBucketPorNome = [];
        DB::table('lotes')->select(['id', ...FotosLote::COLUNAS])->orderBy('id')
            ->chunkById(1000, function ($lotes) use (&$emUsoLocal, &$noBucketPorNome) {
                foreach ($lotes as $lote) {
                    foreach (FotosLote::COLUNAS as $coluna) {
                        $caminho = $lote->{$coluna};
                        if (blank($caminho)) {
                            continue;
                        }
                        if (FotosLote::noBucket($caminho)) {
                            $noBucketPorNome[basename($caminho)] = $caminho;
                        } else {
                            $emUsoLocal[ltrim($caminho, '/')] = true;
                        }
                    }
                }
            });

        $c = ['apagaveis' => 0, 'bytes' => 0, 'em_uso' => 0, 'sem_copia' => 0, 'divergentes' => 0];

        foreach ($pastas as $pasta) {
            foreach ($local->allFiles($pasta) as $arquivo) {
                if (isset($emUsoLocal[$arquivo])) {
                    $c['em_uso']++;

                    continue;
                }

                $destino = $noBucketPorNome[basename($arquivo)] ?? null;
                if (! $destino) {
                    $c['sem_copia']++;

                    continue;
                }

                $tamanho = $local->size($arquivo);
                try {
                    $confere = $bucket->exists($destino) && $bucket->size($destino) === $tamanho;
                } catch (\Throwable) {
                    $confere = false;
                }
                if (! $confere) {
                    $c['divergentes']++;
                    $this->warn("  ⚠ {$arquivo}: cópia no bucket ausente ou com tamanho diferente — mantido.");

                    continue;
                }

                $c['apagaveis']++;
                $c['bytes'] += $tamanho;
                if ($executar) {
                    $local->delete($arquivo);
                }
            }
        }

        $this->table(
            [$executar ? 'Apagados' : 'Apagáveis', 'Espaço', 'Ainda em uso (local)', 'Sem cópia no bucket', 'Divergentes'],
            [[$c['apagaveis'], number_format($c['bytes'] / 1048576, 1, ',', '.').' MB', $c['em_uso'], $c['sem_copia'], $c['divergentes']]],
        );
        $this->comment('Pastas varridas: '.implode(', ', $pastas).'. "Sem cópia no bucket" e "em uso" nunca são apagados.');

        if (! $executar && $c['apagaveis'] > 0) {
            $this->comment('Rode com --executar para apagar.');
        }

        return self::SUCCESS;
    }
}
