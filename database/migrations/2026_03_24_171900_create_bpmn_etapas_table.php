<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bpmn_etapas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('sequential_id');
            $table->foreignId('bpmn_fluxo_id')->constrained('bpmn_fluxos')->cascadeOnDelete();
            
            $table->string('nome'); // Ex: Análise Técnica
            $table->string('codigo_etapa_bpmn')->nullable(); // ID da caixinha gerado pelo desenhador JS
            $table->string('cor_mapa')->default('#cccccc'); // A cor que o lote vai ficar no mapa (Ex: #ff9900)
            $table->integer('tempo_medio_minutos')->default(0); // SLA da etapa
            $table->json('perfis_autorizados')->nullable(); // Quem pode acessar essa etapa
            $table->json('campos_formulario')->nullable(); // O formulário dinâmico em JSON

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX bpmn_etapas_active_unique ON bpmn_etapas (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('bpmn_etapas');
    }
};