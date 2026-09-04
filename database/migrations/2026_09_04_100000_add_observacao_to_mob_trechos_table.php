<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observação livre no trecho viário (pedido da equipe da mobilidade de Piúma,
 * reunião de 2026-09-04 — docs/piuma.txt, ajustes pré-reunião de 08/09).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mob_trechos', function (Blueprint $table) {
            $table->text('observacao')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mob_trechos', function (Blueprint $table) {
            $table->dropColumn('observacao');
        });
    }
};
