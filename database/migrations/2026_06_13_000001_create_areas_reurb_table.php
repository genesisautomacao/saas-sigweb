<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('areas_reurb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable();
            $table->string('nome');
            $table->enum('tipo_reurb', ['Reurb-S', 'Reurb-E', 'Sem Classificação'])->default('Sem Classificação');
            $table->enum('status', ['em_analise', 'regularizado', 'arquivado'])->default('em_analise');
            $table->text('observacao')->nullable();
            $table->float('area_geo')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE areas_reurb ADD COLUMN geo geometry(MULTIPOLYGON, 4326)');
        DB::statement('CREATE INDEX areas_reurb_geo_gist ON areas_reurb USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX areas_reurb_active_unique ON areas_reurb (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('areas_reurb');
    }
};
