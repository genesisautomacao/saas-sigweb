<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B9 (2026-10-19) — Setor Fiscal da INSCRIÇÃO imobiliária (base cartográfica).
 * O número do setor (`name`, texto para não perder o zero à esquerda) compõe a
 * inscrição; um setor agrupa 1+ bairros (`bairros.setor_fiscal_id`) — quadra e
 * lote chegam ao setor pelo bairro. O setor antigo, da PGV, virou `setores_pgv`
 * na migration anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setores_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('sequential_id');
            $table->string('name', 50);
            $table->jsonb('dados_customizados')->nullable(); // item 75
            $table->decimal('area_geo', 15, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE setores_fiscais ADD COLUMN geo geometry(MULTIPOLYGON, 4326)');
        DB::statement('CREATE INDEX setores_fiscais_geo_gist ON setores_fiscais USING GIST (geo)');
        DB::statement('CREATE INDEX setores_fiscais_dados_customizados_gin ON setores_fiscais USING GIN (dados_customizados)');
        DB::statement('CREATE UNIQUE INDEX setores_fiscais_active_unique ON setores_fiscais (tenant_id, sequential_id) WHERE deleted_at IS NULL');
        // O número entra na inscrição: dois setores vivos com o mesmo número na prefeitura = inscrição ambígua
        DB::statement('CREATE UNIQUE INDEX setores_fiscais_tenant_name_unique ON setores_fiscais (tenant_id, name) WHERE deleted_at IS NULL');

        Schema::table('bairros', function (Blueprint $table) {
            $table->foreignId('setor_fiscal_id')->nullable()->after('tenant_id')
                ->constrained('setores_fiscais')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bairros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('setor_fiscal_id');
        });

        Schema::dropIfExists('setores_fiscais');
    }
};
