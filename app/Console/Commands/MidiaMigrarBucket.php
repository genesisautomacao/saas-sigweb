<?php

namespace App\Console\Commands;

use App\Support\Midia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * INF-2 — move anexos, documentos, fotos de rua e 360 avulsas do disco da VPS
 * (`{pasta}/...`) para os buckets do R2 (`{slug}/{pasta}/...`, ver App\Support\Midia).
 *
 * Simulação por padrão (só conta); `--executar` copia cada arquivo, confere o tamanho no
 * bucket e só então troca o caminho no banco (via DB::table — mover arquivo não é
 * alteração de cadastro, não polui a Auditoria). Idempotente e retomável. Inclui
 * registros na lixeira. NÃO apaga os arquivos locais — isso é o `midia:limpar-local`.
 *
 * Anexos de processo: o caminho também vive em processos_digitais.dados_formulario e
 * nos snapshots de processo_respostas.dados — os três são trocados juntos (a lógica de
 * sincronização de anexos compara caminhos exatos; trocar só um lado duplicaria anexos).
 */
class MidiaMigrarBucket extends Command
{
    protected $signature = 'midia:migrar-bucket
        {--tenant= : slug da prefeitura (padrão: todas)}
        {--tipo= : anexos|documentos|chamados|solicitacoes|ordens|panoramas (padrão: todos)}
        {--executar : aplica a migração (sem a opção, só simula)}';

    protected $description = 'Move anexos, documentos e fotos do disco local para os buckets do R2';

    /** Tipos "simples": tabela + colunas com caminho (string ou array JSON). */
    public const TIPOS = [
        'documentos' => ['tabela' => 'documentos', 'colunas' => ['path'], 'json' => false],
        'chamados' => ['tabela' => 'chamados', 'colunas' => ['fotos'], 'json' => true],
        'solicitacoes' => ['tabela' => 'solicitacoes_manutencao', 'colunas' => ['foto_ocorrencia'], 'json' => false],
        'ordens' => ['tabela' => 'ordens_servico', 'colunas' => ['foto_antes', 'foto_depois'], 'json' => false],
        // 104 mil pontos importados já estão no bucket: filtra direto as 360 avulsas legadas.
        'panoramas' => ['tabela' => 'pontos_panoramicos', 'colunas' => ['image_path'], 'json' => false, 'onde' => "image_path like 'panoramas/%'"],
    ];

    private bool $executar = false;

    /** Contadores por tipo. */
    private array $c = [];

    /** Cópias já feitas nesta execução: "origem|destino" => ok. */
    private array $copiados = [];

    public function handle(): int
    {
        $this->executar = (bool) $this->option('executar');

        foreach (['midia', 'fotos'] as $disco) {
            if (! Midia::bucketAtivo($disco)) {
                $this->error("Bucket \"{$disco}\" sem credenciais: cadastre \"".($disco === 'midia' ? 'Cloudflare R2' : 'Cloudflare R2 Fotos').'" em /admin → Configurações de APIs.');

                return self::FAILURE;
            }
        }

        $tenants = DB::table('tenants')
            ->when($this->option('tenant'), fn ($q, $slug) => $q->where('slug', $slug))
            ->pluck('slug', 'id');
        if ($tenants->isEmpty()) {
            $this->error('Nenhuma prefeitura encontrada.');

            return self::FAILURE;
        }

        $tipos = $this->option('tipo') ? [$this->option('tipo')] : array_merge(['anexos'], array_keys(self::TIPOS));
        $this->info($this->executar ? '🚚 MIGRANDO arquivos para o bucket…' : '🔎 SIMULAÇÃO — nada será alterado (use --executar para aplicar).');
        $this->newLine();

        foreach ($tipos as $tipo) {
            $this->c[$tipo] = ['arquivos' => 0, 'bytes' => 0, 'ausentes' => 0, 'migrados' => 0, 'falhas' => 0, 'fora' => 0];
            if ($tipo === 'anexos') {
                $this->migrarAnexos($tenants->all());
            } elseif (isset(self::TIPOS[$tipo])) {
                $this->migrarSimples($tipo, self::TIPOS[$tipo], $tenants->all());
            } else {
                $this->error("Tipo desconhecido: {$tipo}");

                return self::INVALID;
            }
        }

        $this->table(
            ['Tipo', 'Arquivos na VPS', 'Tamanho', 'Sem arquivo no disco', $this->executar ? 'Migrados' : '—', 'Falhas', 'Pasta fora do plano'],
            collect($this->c)->map(fn ($c, $tipo) => [
                $tipo, $c['arquivos'], number_format($c['bytes'] / 1048576, 1, ',', '.').' MB', $c['ausentes'],
                $this->executar ? $c['migrados'] : '—', $c['falhas'], $c['fora'],
            ])->values()->all()
        );

        if (! $this->executar) {
            $this->line('Para aplicar: php artisan midia:migrar-bucket --executar');
        } else {
            $this->line('Arquivos locais preservados. Depois de conferir as telas: php artisan midia:limpar-local');
        }

        return self::SUCCESS;
    }

    /** Caminho no bucket para um caminho legado (as 360 avulsas mudam de pasta). */
    public static function destino(string $legado, string $slug): string
    {
        $legado = ltrim($legado, '/');

        return str_starts_with($legado, 'panoramas/')
            ? $slug.'/panoramicas/avulsas/'.basename($legado)
            : $slug.'/'.$legado;
    }

    /** Copia (ou simula) um arquivo legado. Devolve o caminho novo ou null. */
    private function copiar(string $tipo, string $legado, string $slug): ?string
    {
        if (Midia::noBucket($legado)) {
            return null;
        }

        $destino = self::destino($legado, $slug);
        $disco = Midia::discoBucket($destino);
        if ($disco === null) {
            $this->c[$tipo]['fora']++;

            return null;
        }

        $chave = $legado.'|'.$destino;
        if (isset($this->copiados[$chave])) {
            return $this->copiados[$chave] ? $destino : null;
        }

        $local = Storage::disk(Midia::DISCO_LOCAL);
        if (! $local->exists($legado)) {
            $this->c[$tipo]['ausentes']++;

            return $this->copiados[$chave] = null;
        }

        $tamanho = $local->size($legado);
        $this->c[$tipo]['arquivos']++;
        $this->c[$tipo]['bytes'] += $tamanho;

        if (! $this->executar) {
            $this->copiados[$chave] = false;

            return null;
        }

        try {
            $bucket = Storage::disk($disco);
            if (! ($bucket->exists($destino) && $bucket->size($destino) === $tamanho)) {
                $stream = $local->readStream($legado);
                $bucket->writeStream($destino, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if ($bucket->size($destino) !== $tamanho) {
                    throw new \RuntimeException('tamanho diferente após o envio');
                }
            }
            $this->c[$tipo]['migrados']++;
            $this->copiados[$chave] = true;

            return $destino;
        } catch (\Throwable $e) {
            $this->c[$tipo]['falhas']++;
            $this->warn("  ⚠ {$tipo}: {$legado} → {$e->getMessage()}");
            $this->copiados[$chave] = false;

            return null;
        }
    }

    private function migrarSimples(string $tipo, array $cfg, array $tenants): void
    {
        if (! Schema::hasTable($cfg['tabela'])) {
            return;
        }

        DB::table($cfg['tabela'])
            ->whereIn('tenant_id', array_keys($tenants))
            ->where(function ($q) use ($cfg) {
                foreach ($cfg['colunas'] as $coluna) {
                    $q->orWhereNotNull($coluna);
                }
            })
            ->when($cfg['onde'] ?? null, fn ($q, $onde) => $q->whereRaw($onde))
            ->orderBy('id')
            ->select(array_merge(['id', 'tenant_id'], $cfg['colunas']))
            ->chunkById(200, function ($linhas) use ($tipo, $cfg, $tenants) {
                foreach ($linhas as $linha) {
                    $slug = $tenants[$linha->tenant_id] ?? null;
                    if (! $slug) {
                        continue;
                    }
                    $novos = [];
                    foreach ($cfg['colunas'] as $coluna) {
                        $valor = $linha->{$coluna};
                        if (blank($valor)) {
                            continue;
                        }
                        if ($cfg['json']) {
                            $lista = json_decode($valor, true);
                            if (! is_array($lista)) {
                                continue;
                            }
                            $mudou = false;
                            foreach ($lista as $i => $caminho) {
                                if (is_string($caminho) && ($novo = $this->copiar($tipo, $caminho, $slug))) {
                                    $lista[$i] = $novo;
                                    $mudou = true;
                                }
                            }
                            if ($mudou) {
                                $novos[$coluna] = json_encode(array_values($lista), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                            }
                        } elseif ($novo = $this->copiar($tipo, $valor, $slug)) {
                            $novos[$coluna] = $novo;
                        }
                    }
                    if ($novos !== []) {
                        DB::table($cfg['tabela'])->where('id', $linha->id)->update($novos);
                    }
                }
            });
    }

    private function migrarAnexos(array $tenants): void
    {
        $ids = DB::table('processo_anexos')->whereIn('tenant_id', array_keys($tenants))
            ->where('caminho_arquivo', 'like', 'processos_anexos/%')->distinct()->pluck('processo_digital_id')
            ->merge(DB::table('processos_digitais')->whereIn('tenant_id', array_keys($tenants))
                ->whereRaw("dados_formulario::text like '%\"processos\\_anexos/%'")->pluck('id'))
            ->unique()->filter()->values();

        foreach ($ids as $processoId) {
            $processo = DB::table('processos_digitais')->where('id', $processoId)->first(['id', 'tenant_id', 'dados_formulario']);
            $slug = $processo ? ($tenants[$processo->tenant_id] ?? null) : null;
            if (! $slug) {
                continue;
            }

            $anexos = DB::table('processo_anexos')->where('processo_digital_id', $processoId)->get(['id', 'caminho_arquivo']);
            $respostas = Schema::hasTable('processo_respostas')
                ? DB::table('processo_respostas')->where('processo_digital_id', $processoId)->get(['id', 'dados'])
                : collect();

            // Todos os caminhos legados do processo (linhas de anexo + JSONs).
            $legados = $anexos->pluck('caminho_arquivo')->filter(fn ($c) => is_string($c) && str_starts_with($c, 'processos_anexos/'))->all();
            $this->coletarLegados(json_decode($processo->dados_formulario ?? 'null', true), $legados);
            foreach ($respostas as $r) {
                $this->coletarLegados(json_decode($r->dados ?? 'null', true), $legados);
            }

            $mapa = [];
            foreach (array_unique($legados) as $legado) {
                if ($novo = $this->copiar('anexos', $legado, $slug)) {
                    $mapa[$legado] = $novo;
                }
            }
            if ($mapa === []) {
                continue;
            }

            DB::transaction(function () use ($mapa, $anexos, $processo, $respostas) {
                foreach ($anexos as $a) {
                    if (isset($mapa[$a->caminho_arquivo])) {
                        DB::table('processo_anexos')->where('id', $a->id)->update(['caminho_arquivo' => $mapa[$a->caminho_arquivo]]);
                    }
                }
                $dados = json_decode($processo->dados_formulario ?? 'null', true);
                if (is_array($dados)) {
                    DB::table('processos_digitais')->where('id', $processo->id)
                        ->update(['dados_formulario' => $this->json(self::trocar($dados, $mapa))]);
                }
                foreach ($respostas as $r) {
                    $d = json_decode($r->dados ?? 'null', true);
                    if (is_array($d)) {
                        DB::table('processo_respostas')->where('id', $r->id)->update(['dados' => $this->json(self::trocar($d, $mapa))]);
                    }
                }
            });
        }
    }

    private function coletarLegados(mixed $valor, array &$lista): void
    {
        if (is_string($valor) && str_starts_with($valor, 'processos_anexos/')) {
            $lista[] = $valor;
        } elseif (is_array($valor)) {
            foreach ($valor as $v) {
                $this->coletarLegados($v, $lista);
            }
        }
    }

    /** Troca, em qualquer profundidade, as strings que são caminhos migrados. */
    public static function trocar(mixed $valor, array $mapa): mixed
    {
        if (is_string($valor)) {
            return $mapa[$valor] ?? $valor;
        }
        if (is_array($valor)) {
            foreach ($valor as $k => $v) {
                $valor[$k] = self::trocar($v, $mapa);
            }
        }

        return $valor;
    }

    private function json(mixed $valor): string
    {
        return json_encode($valor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
