<?php

namespace App\Console\Commands;

use App\Services\ArquivosGerados\ArquivosGeradosService;
use Illuminate\Console\Command;

/** R84-4 — apaga os arquivos gerados vencidos (bucket + lista) e destrava pedidos parados. */
class LimparArquivosGerados extends Command
{
    protected $signature = 'arquivos:limpar-expirados';

    protected $description = 'Apaga os Arquivos Gerados vencidos e marca como erro os pedidos parados há mais de 6 h';

    public function handle(): int
    {
        $r = ArquivosGeradosService::limparExpirados();
        $this->info("Apagados: {$r['apagados']} · pedidos parados marcados como erro: {$r['travados']}");

        return self::SUCCESS;
    }
}
