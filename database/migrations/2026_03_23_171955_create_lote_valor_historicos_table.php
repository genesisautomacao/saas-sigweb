<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lote_valores_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('sequential_id'); // O Padrão SIGWEB
            $table->foreignId('lote_id')->constrained('lotes')->cascadeOnDelete();
            
            $table->integer('ano_vigente'); 
            
            $table->decimal('valor_terreno', 15, 2)->default(0);
            $table->decimal('valor_edificacao', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);

            $table->foreignId('setor_fiscal_id')->nullable()->constrained('setores_fiscais')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        // Garantir o sequencial limpo
        DB::statement('CREATE UNIQUE INDEX lote_valores_hist_active_unique ON lote_valores_historicos (tenant_id, sequential_id) WHERE deleted_at IS NULL');
        // Garantir que um lote não tenha dois valores oficiais no mesmo ano!
        DB::statement('CREATE UNIQUE INDEX lote_ano_unique ON lote_valores_historicos (lote_id, ano_vigente) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('lote_valores_historicos');
    }
};