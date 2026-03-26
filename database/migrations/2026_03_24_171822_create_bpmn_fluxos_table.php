<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bpmn_fluxos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('sequential_id');
            
            $table->string('nome'); // Ex: Aprovação de REURB
            $table->text('descricao')->nullable();
            $table->longText('xml_diagrama')->nullable(); // Onde o desenhador BPMN salva o gráfico
            $table->boolean('ativo')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX bpmn_fluxos_active_unique ON bpmn_fluxos (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('bpmn_fluxos');
    }
};