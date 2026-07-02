<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Entidade Setor (departamento) do motor de Processos Digitais.
 * Base das swimlanes (item 206) e do vínculo etapa → setor responsável.
 * Distinta de `setor_fiscals` (SetorFiscal, do PGV). Ver processosConceito.md §9.6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('sequential_id');

            $table->string('nome');                     // Ex: Secretaria de Obras
            $table->string('cor')->default('#6366f1');  // Cor da swimlane

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX setores_active_unique ON setores (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('setores');
    }
};
