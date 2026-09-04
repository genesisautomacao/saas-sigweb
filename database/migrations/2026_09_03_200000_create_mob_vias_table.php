<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vias Urbanas (Mobilidade Urbana, docs/piuma.txt Onda 6).
 *
 * Reunião com a equipe da mobilidade (2026-09-03): o TRECHO é o segmento do
 * levantamento — a ordem dos vértices é a direção em que o coletor andou e
 * define calçada direita/esquerda; ele NUNCA tem sentido de tráfego e sua
 * geometria nunca é invertida. O fluxo (mão única/dupla + direção) pertence
 * à VIA, tabela própria: em Piúma é 1:1 com os trechos, em outro município a
 * via pode ser a soma de vários trechos. A FK fica no trecho (via_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mob_vias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->string('nome', 255)->nullable();
            $table->string('sentido', 20)->nullable();            // mao_unica | mao_dupla | null = não classificado
            $table->decimal('azimute', 6, 2)->nullable();         // 0–360°, CALCULADO (ST_Azimuth 1º→último vértice)
            $table->decimal('extensao_geo', 12, 2)->nullable();   // metros (PostGIS)
            $table->foreignId('logradouro_id')->nullable()->constrained('logradouros')->nullOnDelete();
            $table->jsonb('dados_customizados')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE mob_vias ADD COLUMN geo geometry(MULTILINESTRING, 4326)');
        DB::statement('CREATE INDEX mob_vias_geo_gist ON mob_vias USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX mob_vias_tenant_sequential_unique ON mob_vias (tenant_id, sequential_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX mob_vias_dados_customizados_gin ON mob_vias USING GIN (dados_customizados)');

        Schema::table('mob_trechos', function (Blueprint $table) {
            $table->foreignId('via_id')->nullable()->constrained('mob_vias')->nullOnDelete();
            $table->index('via_id');
        });

        // O sentido de tráfego sai do trecho (azimute fica = direção do mapeamento)
        Schema::table('mob_trechos', function (Blueprint $table) {
            $table->dropColumn('sentido');
        });
    }

    public function down(): void
    {
        Schema::table('mob_trechos', function (Blueprint $table) {
            $table->string('sentido', 20)->nullable();
        });
        Schema::table('mob_trechos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('via_id');
        });
        Schema::dropIfExists('mob_vias');
    }
};
