<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R67-1 — Campos customizados por município (lotes, edificações, unidades imobiliárias).
 * Cada tenant define campos extras além dos básicos do sistema; os valores ficam na coluna
 * JSON `dados_customizados` da respectiva tabela (chave = slug definido aqui).
 * O slug é snake_case porque também é o nome da property no GeoJSON da importação GIS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campos_customizados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('entidade'); // lote | edificacao | unidade
            $table->string('label');
            $table->string('slug');
            $table->string('tipo')->default('texto'); // texto|numero|selecao|multipla|data|sim_nao
            $table->json('opcoes')->nullable();
            $table->boolean('obrigatorio')->default(false);
            $table->boolean('na_coleta')->default(true);
            $table->integer('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'entidade', 'ativo']);
        });

        // Unique parcial: permite recriar um campo excluído (soft delete) com o mesmo slug.
        DB::statement('CREATE UNIQUE INDEX campos_customizados_slug_unique ON campos_customizados (tenant_id, entidade, slug) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('campos_customizados');
    }
};
