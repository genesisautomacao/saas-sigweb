<?php

namespace App\Console\Commands;

use App\Support\FotosLote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * R79-1 — move as fotos dos lotes do disco da VPS (`lotes_fotos/...`) para o bucket
 * público `sigweb-fotos` (`{slug}/lotes_fotos/...`).
 *
 * Simulação por padrão (só conta); `--executar` copia cada foto, confere o tamanho no
 * bucket e só então troca o caminho no banco. Idempotente e retomável: o que já está
 * no bucket é ignorado. NÃO apaga os arquivos locais (backup) — isso é o
 * `fotos:limpar-local`. Inclui lotes na lixeira (podem ser restaurados).
 */
class FotosMigrarBucket extends Command
{
    protected $signature = 'fotos:migrar-bucket
        {--tenant= : slug da prefeitura (padrão: todas)}
        {--executar : aplica a migração (sem a opção, só simula)}';

    protected $description = 'Move as fotos dos lotes do disco local para o bucket público (R2 sigweb-fotos)';

    public function handle(): int
    {
        if (! FotosLote::bucketAtivo()) {
            $this->error('Bucket de fotos sem credenciais: cadastre "Cloudflare R2 Fotos" em /admin → Configurações de APIs.');

            return self::FAILURE;
        }

        $executar = (bool) $this->option('executar');
        $tenants = DB::table('tenants')
            ->when($this->option('tenant'), fn ($q, $slug) => $q->where('slug', $slug))
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);

        if ($tenants->isEmpty()) {
            $this->error('Nenhuma prefeitura encontrada.');

            return self::FAILURE;
        }

        $this->info($executar ? '🚚 MIGRANDO fotos para o bucket…' : '🔎 SIMULAÇÃO — nada será alterado (use --executar para aplicar).');
        $this->newLine();

        $linhas = [];
        $pastas = [];
        $totais = ['lotes' => 0, 'fotos' => 0, 'ausentes' => 0, 'bytes' => 0, 'migradas' => 0, 'falhas' => 0];
        $local = Storage::disk(FotosLote::DISCO_LOCAL);

        foreach ($tenants as $tenant) {
            $c = ['lotes' => 0, 'fotos' => 0, 'ausentes' => 0, 'bytes' => 0, 'migradas' => 0, 'falhas' => 0];

            $this->candidatos($tenant->id)->chunkById(200, function ($lotes) use (&$c, &$pastas, $tenant, $executar, $local) {
                foreach ($lotes as $lote) {
                    if (! FotosLote::temFotoLocal($lote)) {
                        continue;
                    }
                    $c['lotes']++;

                    if ($executar) {
                        foreach (FotosLote::migrarLote($lote, $tenant->slug) as $coluna => $status) {
                            $c['fotos']++;
                            match (true) {
                                $status === 'migrada' => $c['migradas']++,
                                $status === 'ausente' => $c['ausentes']++,
                                default => $c['falhas']++,
                            };
                            if (str_starts_with($status, 'falhou')) {
                                $this->warn("  ⚠ lote {$lote->id} {$coluna}: {$status}");
                            }
                        }

                        continue;
                    }

                    foreach (FotosLote::COLUNAS as $coluna) {
                        $caminho = $lote->{$coluna};
                        if (blank($caminho) || FotosLote::noBucket($caminho)) {
                            continue;
                        }
                        $c['fotos']++;
                        $pastas[dirname(ltrim($caminho, '/'))] = true;
                        if ($local->exists($caminho)) {
                            $c['bytes'] += $local->size($caminho);
                        } else {
                            $c['ausentes']++;
                        }
                    }
                }
            });

            if ($c['fotos'] === 0) {
                continue;
            }

            foreach ($c as $k => $v) {
                $totais[$k] += $v;
            }
            $linhas[] = $this->linha($tenant->slug, $c, $executar);
        }

        if ($linhas === []) {
            $this->info('✓ Nenhuma foto no disco local — tudo já está no bucket.');

            return self::SUCCESS;
        }

        $this->table($this->cabecalho($executar), [...$linhas, $this->linha('TOTAL', $totais, $executar)]);

        if (! $executar) {
            $this->comment('"Ausentes" = caminho gravado no banco sem arquivo no disco (ficam como estão).');
            $this->comment('Pastas locais encontradas: '.implode(', ', array_keys($pastas)).' — se houver alguma além de lotes_fotos e lotes/fotos-frontais, passe-a no fotos:limpar-local --pasta=...');
            $this->comment('Rode com --executar para migrar. Os arquivos locais NÃO são apagados.');
        } elseif ($totais['falhas'] > 0) {
            $this->warn("{$totais['falhas']} foto(s) falharam e continuam no disco local — rode o comando de novo para tentar outra vez.");
        } else {
            $this->info('✅ Migração concluída. Quando quiser liberar o disco: php artisan fotos:limpar-local');
        }

        return self::SUCCESS;
    }

    /** Lotes (inclusive na lixeira) com alguma foto que não esteja no bucket. */
    private function candidatos(int $tenantId)
    {
        return DB::table('lotes')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                foreach (FotosLote::COLUNAS as $coluna) {
                    $q->orWhere(fn ($w) => $w->whereNotNull($coluna)
                        ->where($coluna, '<>', '')
                        ->where($coluna, 'not like', '%/'.FotosLote::PASTA.'/%'));
                }
            })
            ->select(['id', ...FotosLote::COLUNAS])
            ->orderBy('id');
    }

    private function cabecalho(bool $executar): array
    {
        return $executar
            ? ['Prefeitura', 'Lotes', 'Fotos', 'Migradas', 'Ausentes', 'Falhas']
            : ['Prefeitura', 'Lotes', 'Fotos', 'Ausentes', 'Tamanho'];
    }

    private function linha(string $nome, array $c, bool $executar): array
    {
        return $executar
            ? [$nome, $c['lotes'], $c['fotos'], $c['migradas'], $c['ausentes'], $c['falhas']]
            : [$nome, $c['lotes'], $c['fotos'], $c['ausentes'], number_format($c['bytes'] / 1048576, 1, ',', '.').' MB'];
    }
}
