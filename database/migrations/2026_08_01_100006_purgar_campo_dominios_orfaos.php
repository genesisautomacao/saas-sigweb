<?php

use App\Services\Coleta\CampoDominioService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PoC Tangará — remove de `campo_dominios` as linhas de campos que DEIXARAM de ser
 * campos padrão do sistema (edificação inteira, fiscais da unidade, situacao_quadra).
 * Sem isso, a tela "Campos Padrão do Sistema" listaria linhas órfãs cujo botão
 * "Personalizar" cairia num 404 (a entidade saiu do PADROES).
 *
 * A personalização equivalente agora vive no cadastro do CAMPO CUSTOMIZADO (kit).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('campo_dominios')) {
            return;
        }

        $validos = [];
        foreach (CampoDominioService::PADROES as $entidade => $campos) {
            foreach (array_keys($campos) as $campo) {
                $validos[] = $entidade.'.'.$campo;
            }
        }

        $removidos = 0;
        foreach (DB::table('campo_dominios')->get(['id', 'entidade', 'campo']) as $linha) {
            if (! in_array($linha->entidade.'.'.$linha->campo, $validos, true)) {
                DB::table('campo_dominios')->where('id', $linha->id)->delete();
                $removidos++;
            }
        }

        echo "  ── campo_dominios: {$removidos} linha(s) órfã(s) removidas" . PHP_EOL;
    }

    public function down(): void
    {
        // Linhas órfãs não são recriáveis (nem devem ser).
    }
};
