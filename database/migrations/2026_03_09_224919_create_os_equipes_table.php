<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('os_equipe', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            
            // 🛑 A SUA REFATORAÇÃO DE MESTRE: Link com Pessoas (Módulo Administrativo)
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('os_equipes');
    }
};
