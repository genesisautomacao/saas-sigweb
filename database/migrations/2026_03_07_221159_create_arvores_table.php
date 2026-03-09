<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. TABELA PRINCIPAL DE ÁRVORES
        Schema::create('arvores', function (Blueprint $table) {
            $table->id();

            // Padrão SaaS
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->uuid('code')->unique();
            $table->string('address')->nullable(); // Referência manual (Ex: Em frente ao nº 10)
            
            // Campos Florestais / Arborização
            $table->string('botanical_species')->nullable(); // Espécie Botânica
            $table->string('botanical_family')->nullable();  // Família Botânica
            $table->string('size')->nullable();              // Porte (Pequeno, Médio, Grande)
            $table->decimal('trunk_diameter_dap', 8, 2)->nullable(); // DAP (Diâmetro Altura do Peito)
            $table->decimal('canopy_diameter', 8, 2)->nullable();    // Diâmetro da Copa
            $table->decimal('total_height', 8, 2)->nullable();       // Altura Total
            $table->decimal('canopy_height', 8, 2)->nullable();      // Altura da Primeira Forquilha/Copa
            $table->string('phytosanitary_condition')->nullable();   // Condição Fitossanitária (Boa, Regular, Ruim, Morta)
            $table->string('general_state')->nullable();             // Estado Geral
            $table->string('root_system')->nullable();               // Sistema Radicular (Aparente, Danificando calçada, etc)
            $table->string('urban_interferences')->nullable();       // Interferências (Rede Elétrica, Placa, etc)
            $table->integer('risk_potential')->nullable();           // Potencial de Risco (1 a 5, por exemplo)
            $table->text('observations')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // Índice único para evitar duplicidade no sequencial por prefeitura
        DB::statement('CREATE UNIQUE INDEX arvores_tenant_id_sequential_id_unique ON arvores (tenant_id, sequential_id) WHERE deleted_at IS NULL');

        // Geometria (PONTO) e Índice Espacial (GIST)
        DB::statement('ALTER TABLE arvores ADD COLUMN geo geometry(POINT, 4326)');
        DB::statement('CREATE INDEX arvores_geo_gist ON arvores USING GIST (geo)');

        // 2. TABELA PIVÔ (ARVORE_LOGRADOURO)
        Schema::create('arvore_logradouro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arvore_id')->constrained('arvores')->cascadeOnDelete();
            $table->foreignId('logradouro_id')->constrained('logradouros')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arvore_logradouro');
        Schema::dropIfExists('arvores');
    }
};