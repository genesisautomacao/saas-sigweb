<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pgv_parametros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('sequential_id'); // O Padrão SIGWEB
            
            $table->string('nome_padrao'); 
            $table->decimal('valor_m2_terreno', 12, 2)->default(0);
            $table->decimal('valor_m2_edificacao', 12, 2)->default(0);
            $table->json('fatores_adicionais')->nullable(); 

            $table->timestamps();
            $table->softDeletes(); // O Padrão SIGWEB
        });

        // Índice Único para Tenant + Sequencial Ativo
        DB::statement('CREATE UNIQUE INDEX pgv_parametros_active_unique ON pgv_parametros (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('pgv_parametros');
    }
};