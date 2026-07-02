<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_quadras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->string('code')->nullable();  // código da seção/face (relatório item 236)
            $table->string('name')->nullable();
            $table->foreignId('quadra_id')->constrained('quadras')->cascadeOnDelete();
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->foreignId('logradouro_id')->nullable()->constrained('logradouros')->nullOnDelete();
            $table->decimal('extensao_geo', 12, 2)->nullable(); // comprimento (ST_Length)

            // Saída do motor PGV (itens 233/234)
            $table->decimal('valor_m2_calculado', 14, 2)->nullable();
            $table->decimal('distancia_polo', 12, 2)->nullable();
            $table->foreignId('pgv_polo_id')->nullable()->constrained('pgv_polos')->nullOnDelete();
            $table->foreignId('setor_fiscal_id')->nullable()->constrained('setores_fiscais')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        // Geometria linear da face de quadra (MULTILINESTRING)
        DB::statement('ALTER TABLE face_quadras ADD COLUMN geo geometry(MultiLineString, 4326)');
        DB::statement('CREATE INDEX face_quadras_geo_gist ON face_quadras USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX face_quadras_tenant_seq_unique ON face_quadras (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void { Schema::dropIfExists('face_quadras'); }
};
