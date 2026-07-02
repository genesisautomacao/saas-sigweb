<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configuração do motor PGV — 1 registro por tenant (itens 230/243)
        Schema::create('pgv_configuracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->json('fatores')->nullable(); // multiplicadores de homogeneização por atributo
            $table->foreignId('lote_paradigma_id')->nullable()->constrained('lotes')->nullOnDelete();
            $table->decimal('percentual_valor_venal', 6, 2)->default(100); // % do valor venal p/ base de cálculo
            $table->decimal('limite_aumento_iptu', 6, 2)->nullable();       // limitador de aumento (%)
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void { Schema::dropIfExists('pgv_configuracoes'); }
};
