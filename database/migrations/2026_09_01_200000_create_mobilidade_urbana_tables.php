<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Mobilidade Urbana (Piúma/ES — docs/piuma.txt, Onda 0).
 *
 * 6 entidades geográficas + 1 catálogo cobrem os 19 layers do levantamento.
 * Convenções do sistema: tenant_id cascade, sequential_id com índice único
 * parcial (WHERE deleted_at IS NULL — compatível com o Importador GIS),
 * dados_customizados JSONB + GIN, geo via DB::statement + índice GIST,
 * colunas de domínio SEM CHECK constraint (white-label — ver CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Catálogo de tipos de sinalização (decisão 6.1: a placa aponta
        //    para um tipo pré-cadastrado com cor/ícone; só a posição varia).
        //    Sem SoftDeletes: FK restrict impede apagar tipo em uso; "ativo"
        //    aposenta sem quebrar histórico.
        Schema::create('mob_tipos_sinalizacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('tipo', 20); // vertical | horizontal (sem CHECK)
            $table->string('cor', 20)->nullable();
            $table->string('icone', 255)->nullable(); // upload png/jpg (molde CategoriaChamado)
            $table->string('codigo_ctb', 20)->nullable(); // R-1, A-12...
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name', 'tipo']);
        });

        // ── Trechos viários (a joia: 612 trechos com 26 atributos).
        //    Sem "name" (decisão 6.3): referência é o sequential_id.
        //    Direção = ordem dos vértices; azimute é CALCULADO (read-only).
        Schema::create('mob_trechos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->string('tipologia_da_via', 50)->nullable();           // Avenida | Rua | Rodovia | Beco/Viela
            $table->string('tipo_de_pavimentacao', 50)->nullable();       // Asfalto | Paralelepípedo | Terra...
            $table->string('estado_conservacao_pavimentacao', 50)->nullable();
            $table->string('classe_faixa_rodagem', 50)->nullable();       // Pista Simples | Pista Dupla
            $table->string('dimensionamento_da_via', 50)->nullable();     // faixas de largura
            $table->string('sentido', 20)->nullable();                    // mao_unica | mao_dupla | null = não classificado
            $table->decimal('azimute', 6, 2)->nullable();                 // 0–360°, calculado via ST_Azimuth
            $table->decimal('extensao_geo', 12, 2)->nullable();           // metros (PostGIS)
            $table->foreignId('logradouro_id')->nullable()->constrained('logradouros')->nullOnDelete();
            $table->jsonb('dados_customizados')->nullable();              // calçadas/estacionamento/vegetação (kit Piúma)

            $table->timestamps();
            $table->softDeletes();
        });

        // ── Sinalização viária (832 pontos: sinalização + estacionamento).
        Schema::create('mob_sinalizacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->foreignId('tipo_sinalizacao_id')->nullable()
                ->constrained('mob_tipos_sinalizacao')->restrictOnDelete();
            $table->text('descricao_original')->nullable(); // texto cru da coleta (auditoria/conferência)
            $table->text('observacao')->nullable();
            $table->jsonb('dados_customizados')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // ── Pontos de interesse (184: comércio, educação, saúde, religioso,
        //    turismo/lazer/esporte, indústria, posto de combustível).
        Schema::create('mob_pontos_interesse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->string('categoria', 50)->nullable();
            $table->string('name', 255)->nullable();
            $table->string('numero', 50)->nullable();
            $table->jsonb('dados_customizados')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // ── Eixos de mobilidade (ciclovia, eixo comercial, rota de carga,
        //    rodovia recortada). Extras da rodovia DER-ES → dados_customizados.
        Schema::create('mob_eixos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->string('tipo', 50)->nullable(); // ciclovia | eixo_comercial | rota_carga | rodovia
            $table->string('name', 255)->nullable(); // como veio do levantamento (decisão 6.7)
            $table->decimal('extensao_geo', 12, 2)->nullable(); // METROS (exibição em km — decisão 6.2)
            $table->jsonb('dados_customizados')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // ── Zonas de estudo (zonas O/D, quadrantes, polo industrial,
        //    setores censitários IBGE).
        Schema::create('mob_zonas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->string('tipo', 50)->nullable(); // zona_od | quadrante | polo_industrial | setor_censitario
            $table->string('name', 255)->nullable();
            $table->string('codigo', 50)->nullable(); // setor IBGE (15 dígitos)
            $table->string('situacao', 30)->nullable(); // Urbana | Rural
            $table->decimal('origens', 8, 2)->nullable();  // % do estudo O/D
            $table->decimal('destinos', 8, 2)->nullable(); // % do estudo O/D
            $table->decimal('area_geo', 14, 2)->nullable(); // m² (recalculada via PostGIS — 6.6)
            $table->jsonb('dados_customizados')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'codigo']);
        });

        // ── Fluxos O/D (linhas de desejo — 63; espessura ∝ valores no mapa).
        Schema::create('mob_fluxos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->string('destino', 50)->nullable(); // centro | norte | sul | leste | nordeste | sudoeste
            $table->integer('valores')->default(0);    // volume de deslocamentos
            $table->jsonb('dados_customizados')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // ── Geometrias + índices (fora do Schema builder, padrão do projeto)
        $geos = [
            'mob_trechos' => 'MULTILINESTRING',
            'mob_sinalizacoes' => 'POINT',
            'mob_pontos_interesse' => 'POINT',
            'mob_eixos' => 'MULTILINESTRING',
            'mob_zonas' => 'MULTIPOLYGON',
            'mob_fluxos' => 'MULTILINESTRING',
        ];

        foreach ($geos as $tabela => $tipo) {
            DB::statement("ALTER TABLE {$tabela} ADD COLUMN geo geometry({$tipo}, 4326)");
            DB::statement("CREATE INDEX {$tabela}_geo_gist ON {$tabela} USING GIST (geo)");
            DB::statement("CREATE UNIQUE INDEX {$tabela}_tenant_sequential_unique ON {$tabela} (tenant_id, sequential_id) WHERE deleted_at IS NULL");
            DB::statement("CREATE INDEX {$tabela}_dados_customizados_gin ON {$tabela} USING GIN (dados_customizados)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mob_fluxos');
        Schema::dropIfExists('mob_zonas');
        Schema::dropIfExists('mob_eixos');
        Schema::dropIfExists('mob_pontos_interesse');
        Schema::dropIfExists('mob_sinalizacoes');
        Schema::dropIfExists('mob_trechos');
        Schema::dropIfExists('mob_tipos_sinalizacao');
    }
};
