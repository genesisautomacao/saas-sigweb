<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('parametros_urbanos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id'); // Padrão do seu sistema
            $table->uuid('code')->unique();

            // Vínculo com a Zona (Pode ser por ID ou Sigla, seguindo seu padrão de regras)
            $table->foreignId('zona_id')->constrained('zonas')->cascadeOnDelete();
            
            // Regras de Parcelamento / Unificação
            $table->decimal('area_minima', 12, 2)->nullable();
            $table->decimal('area_maxima', 12, 2)->nullable();
            $table->decimal('testada_minima', 10, 2)->nullable();
            $table->decimal('testada_maxima', 10, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_urbanos');
    }
};