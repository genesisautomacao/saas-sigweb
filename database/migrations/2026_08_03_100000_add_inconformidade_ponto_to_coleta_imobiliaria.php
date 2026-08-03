<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PoC Tangará — o app de coletas marca o PONTO GPS da inconformidade
 * (dados_vistoria.inconformidade_ponto, no contrato antigo). Com a remoção de
 * lotes.dados_vistoria esse dado ficou sem casa — e ele é dado da COLETA.
 * Coluna jsonb {lat, lon} na coleta_imobiliaria; o pull devolve no formato
 * antigo (dentro de dados_vistoria) para o app publicado não mudar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('coleta_imobiliaria', 'inconformidade_ponto')) {
            DB::statement('ALTER TABLE coleta_imobiliaria ADD COLUMN inconformidade_ponto jsonb NULL');
        }

        // Recupera pontos que ainda estejam em backups da coluna antiga? Não há —
        // lotes.dados_vistoria tinha 3 registros e foi descartada como campo morto.
    }

    public function down(): void
    {
        if (Schema::hasColumn('coleta_imobiliaria', 'inconformidade_ponto')) {
            Schema::table('coleta_imobiliaria', fn (Blueprint $t) => $t->dropColumn('inconformidade_ponto'));
        }
    }
};
