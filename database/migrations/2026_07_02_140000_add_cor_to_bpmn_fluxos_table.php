<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cor customizada por fluxo — usada para colorir os lotes em processo no mapa
 * (camada "Processos Digitais", um toggle por fluxo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bpmn_fluxos', function (Blueprint $table) {
            if (! Schema::hasColumn('bpmn_fluxos', 'cor')) {
                $table->string('cor')->default('#3b82f6')->after('nome'); // hex #rrggbb
            }
        });
    }

    public function down(): void
    {
        Schema::table('bpmn_fluxos', function (Blueprint $table) {
            if (Schema::hasColumn('bpmn_fluxos', 'cor')) {
                $table->dropColumn('cor');
            }
        });
    }
};
