<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pgv_amostras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->foreignId('lote_id')->nullable()->constrained('lotes')->nullOnDelete();
            $table->decimal('valor_m2', 14, 2)->default(0); // valor de mercado observado (m²)
            $table->integer('idade_aparente')->nullable();
            $table->string('estado_conservacao')->nullable();
            $table->string('tipologia')->nullable();
            $table->string('padrao_cub')->nullable();
            $table->decimal('area_terreno', 12, 2)->nullable();
            $table->decimal('area_edificacao', 12, 2)->nullable();
            $table->boolean('espuria')->default(false); // item 232
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Amostra georreferenciada (item 225) — clique no mapa
        DB::statement('ALTER TABLE pgv_amostras ADD COLUMN geo geometry(Point, 4326)');
        DB::statement('CREATE UNIQUE INDEX pgv_amostras_tenant_seq_unique ON pgv_amostras (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void { Schema::dropIfExists('pgv_amostras'); }
};
