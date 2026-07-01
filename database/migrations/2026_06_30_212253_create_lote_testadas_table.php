<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lote_testadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lote_id')->constrained('lotes')->cascadeOnDelete();
            $table->foreignId('logradouro_id')->nullable()->constrained('logradouros')->nullOnDelete();
            $table->foreignId('secao_logradouro_id')->nullable()->constrained('secoes_logradouro')->nullOnDelete();
            $table->enum('tipo', ['principal', 'secundaria', 'lateral', 'fundos'])->default('secundaria');
            $table->decimal('comprimento', 10, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lote_testadas');
    }
};
