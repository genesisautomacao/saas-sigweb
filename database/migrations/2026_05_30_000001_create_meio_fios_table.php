<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meio_fios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->uuid('code')->unique();

            // Atributos cadastrais
            $table->string('material', 50)->nullable();
            $table->string('estado_conservacao', 30)->nullable();
            $table->decimal('extensao_geo', 12, 2)->nullable();

            // Vínculos opcionais
            $table->foreignId('logradouro_id')->nullable()->constrained('logradouros')->nullOnDelete();

            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // Índice único para garantir sequencial por prefeitura
        DB::statement('CREATE UNIQUE INDEX meio_fios_tenant_id_sequential_id_unique ON meio_fios (tenant_id, sequential_id) WHERE deleted_at IS NULL');

        // Geometria (LINESTRING) + índice espacial
        DB::statement('ALTER TABLE meio_fios ADD COLUMN geo geometry(MULTILINESTRING, 4326)');
        DB::statement('CREATE INDEX meio_fios_geo_gist ON meio_fios USING GIST (geo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('meio_fios');
    }
};
