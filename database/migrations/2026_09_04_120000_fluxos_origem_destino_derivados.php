<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fluxos O/D — origem × destino (docs/piuma.txt, 2026-09-04).
 *
 * O campo `fluxo` do levantamento (importado como `destino`) nomeia a ponta
 * COMPARTILHADA das linhas de cada grupo = a ORIGEM (confirmado por PostGIS e
 * pelos volumes: 251 viagens Nordeste → Central, não o contrário). Renomeia a
 * coluna para `origem_regiao` e cria as duas zonas DERIVADAS da geometria:
 * `origem_zona` (zona O/D da ponta inicial) e `destino_zona` (zona O/D da ponta
 * final) — recalculadas por MobFluxo::recalcularOrigensDestinos().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mob_fluxos', function (Blueprint $table) {
            $table->renameColumn('destino', 'origem_regiao');
        });
        Schema::table('mob_fluxos', function (Blueprint $table) {
            $table->string('origem_zona', 120)->nullable();
            $table->string('destino_zona', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mob_fluxos', function (Blueprint $table) {
            $table->dropColumn(['origem_zona', 'destino_zona']);
        });
        Schema::table('mob_fluxos', function (Blueprint $table) {
            $table->renameColumn('origem_regiao', 'destino');
        });
    }
};
