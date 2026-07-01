<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('secoes_logradouro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->uuid('code')->unique();

            $table->string('name')->nullable();
            $table->string('tipo_pavimentacao', 50)->nullable();
            $table->decimal('extensao_geo', 12, 2)->nullable();

            $table->foreignId('logradouro_id')->constrained('logradouros')->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        // Índice único para garantir sequencial por prefeitura
        DB::statement('CREATE UNIQUE INDEX secoes_logradouro_tenant_id_sequential_id_unique ON secoes_logradouro (tenant_id, sequential_id) WHERE deleted_at IS NULL');

        // Geometria (LINESTRING) + índice espacial
        DB::statement('ALTER TABLE secoes_logradouro ADD COLUMN geo geometry(MULTILINESTRING, 4326)');
        DB::statement('CREATE INDEX secoes_logradouro_geo_gist ON secoes_logradouro USING GIST (geo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('secoes_logradouro');
    }
};
