<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Processos — dono atual do processo (analista responsável).
 * Persiste o "encaminhar para analista específico" (itens 133/144/212) e habilita
 * os filtros "Meus processos" (217) e "Fila geral / não atribuídos" (218).
 * null = na fila geral da etapa (qualquer analista do perfil pode pegar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processos_digitais', function (Blueprint $table) {
            $table->foreignId('analista_id')->nullable()->after('etapa_atual_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('processos_digitais', function (Blueprint $table) {
            $table->dropConstrainedForeignId('analista_id');
        });
    }
};
