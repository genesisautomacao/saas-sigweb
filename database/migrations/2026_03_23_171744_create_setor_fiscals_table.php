<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setores_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('sequential_id'); // O Padrão SIGWEB
            $table->foreignId('pgv_parametro_id')->constrained('pgv_parametros')->restrictOnDelete();
            
            $table->string('nome'); 
            $table->text('descricao')->nullable();

            $table->timestamps();
            $table->softDeletes(); // O Padrão SIGWEB
        });

        // A MÁGICA POSTGIS: Exatamente igual ao Lote
        DB::statement('ALTER TABLE setores_fiscais ADD COLUMN geo geometry(MULTIPOLYGON, 4326)');
        DB::statement('CREATE INDEX setores_fiscais_geo_gist ON setores_fiscais USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX setores_fiscais_active_unique ON setores_fiscais (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('setores_fiscais');
    }
};