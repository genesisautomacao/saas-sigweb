<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Tabela de CNAEs (Dicionário Nacional)
        Schema::create('cnaes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index(); // Caso queira isolar por cidade futuramente

            $table->string('codigo')->index(); // Ex: "01.21-1"
            $table->text('descricao');         // Ex: "Horticultura"

            // O PULO DO GATO: JSONB para guardar array de classificações ["CS1", "SC2"]
            $table->jsonb('classificacoes')->default('[]');

            $table->timestamps();
        });

        // 2. Tabela de Regras (Matriz de Viabilidade)
        Schema::create('zoneamento_regras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();

            // Usaremos a Sigla para facilitar o cruzamento (Ex: "ZR1")
            // Idealmente indexamos para a busca ser instantânea
            $table->string('zona_sigla')->index();

            // A classificação específica (Ex: "H1", "CS2")
            $table->string('classificacao')->index();

            // O Veredito
            $table->enum('status', ['permitido', 'permissivel', 'proibido'])->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoneamento_regras');
        Schema::dropIfExists('cnaes');
    }
};