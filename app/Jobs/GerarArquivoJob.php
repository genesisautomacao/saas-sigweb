<?php

namespace App\Jobs;

use App\Models\ArquivoGerado;
use App\Services\ArquivosGerados\ArquivosGeradosService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * R84-4 — base dos jobs que geram um arquivo pesado (fila `sigweb-fila` na VPS).
 * A subclasse implementa gerar(): escreve o arquivo dentro de $dirTrabalho e devolve
 * [caminho local, nome para download, resumo]. A base cuida do status, do upload, do
 * aviso no sininho, do erro e da limpeza da pasta temporária.
 */
abstract class GerarArquivoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Uma tentativa só: falhou = aviso ao usuário (refazer 30 min de PDF sozinho não ajuda). */
    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $arquivoId) {}

    /** @return array{0:string,1:string,2:?string} [caminho local, nome do arquivo, resumo] */
    abstract protected function gerar(ArquivoGerado $arquivo, string $dirTrabalho): array;

    public function handle(): void
    {
        $arquivo = ArquivoGerado::find($this->arquivoId);
        if (! $arquivo || $arquivo->status === 'pronto') {
            return;
        }

        @ini_set('memory_limit', '2048M');
        $arquivo->forceFill(['status' => 'processando', 'progresso' => 0, 'iniciado_em' => now(), 'mensagem' => null])->save();

        $dir = storage_path('app/tmp/arquivo-'.$arquivo->id.'-'.uniqid());
        File::ensureDirectoryExists($dir);

        try {
            [$local, $nome, $resumo] = $this->gerar($arquivo, $dir);
            ArquivosGeradosService::guardar($arquivo, $local, $nome, $resumo);
            ArquivosGeradosService::notificarPronto($arquivo->fresh());
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function failed(?\Throwable $e): void
    {
        $arquivo = ArquivoGerado::find($this->arquivoId);
        if (! $arquivo) {
            return;
        }

        Log::error("[GerarArquivoJob] #{$arquivo->id} {$arquivo->tipo}: ".($e?->getMessage() ?? 'sem detalhe'));

        $arquivo->forceFill([
            'status' => 'erro',
            'mensagem' => 'Falha ao gerar: '.mb_strimwidth((string) $e?->getMessage(), 0, 300, '…'),
            'concluido_em' => now(),
            'expira_em' => now()->addDays(ArquivoGerado::DIAS_VALIDADE),
        ])->save();

        ArquivosGeradosService::notificarErro($arquivo);
    }

    /** Atualiza o progresso (0..100) sem disparar eventos — a lista mostra em tempo real. */
    protected function progresso(ArquivoGerado $arquivo, int $pct): void
    {
        $arquivo->newQuery()->whereKey($arquivo->id)->update(['progresso' => max(0, min(99, $pct)), 'updated_at' => now()]);
    }
}
