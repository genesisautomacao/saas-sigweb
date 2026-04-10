<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pontos_panoramicos', function (Blueprint $table) {
            $table->id();

            // Padrão SaaS SIGWEB
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->uuid('code')->unique();
            
            // Dados da Imagem 360
            $table->string('titulo'); // Ex: Praça da Matriz
            $table->string('image_path')->nullable(); // Onde a foto vai ficar salva no storage
            $table->string('image_url_simulacao')->nullable(); // Para usarmos nosso link de teste (Pannellum)
            $table->date('data_captura')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });

        // Índice único para evitar duplicidade no sequencial por prefeitura
        DB::statement('CREATE UNIQUE INDEX pontos_panoramicos_tenant_id_sequential_id_unique ON pontos_panoramicos (tenant_id, sequential_id) WHERE deleted_at IS NULL');

        // Geometria (PONTO) e Índice Espacial (GIST) - Igual ao da Árvore
        DB::statement('ALTER TABLE pontos_panoramicos ADD COLUMN geo geometry(POINT, 4326)');
        DB::statement('CREATE INDEX pontos_panoramicos_geo_gist ON pontos_panoramicos USING GIST (geo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pontos_panoramicos');
    }
};