<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('os_materiais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            
            // O que gastou e de onde tirou (Ex: Lâmpada do Caminhão 01)
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('local_estoque_id')->constrained('locais_estoque')->cascadeOnDelete();
            
            $table->decimal('quantidade', 10, 2);
        });
    }

    public function down(): void { Schema::dropIfExists('os_materiais'); }
};