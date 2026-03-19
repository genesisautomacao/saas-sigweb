<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cadastros_sociais', function (Blueprint $table) {
            $table->id();
            
            // Padrão SaaS do seu sistema
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable(); // Para manter seu padrão numérico

            // Relacionamentos Fortes (Quem é a família e onde moram)
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete()->comment('Responsável Familiar (RF)');
            $table->foreignId('unidade_imobiliaria_id')->nullable()->constrained('unidade_imobiliarias')->nullOnDelete()->comment('Vínculo com o Mapa/Geografia');

            // Dados Oficiais (CadÚnico)
            $table->string('nis', 15)->nullable()->comment('Número de Identificação Social');
            $table->integer('quantidade_membros')->default(1)->comment('Composição Familiar');
            $table->decimal('renda_familiar_total', 10, 2)->nullable();
            $table->decimal('renda_per_capita', 10, 2)->nullable();

            // Indicadores de Inteligência (Para os Gráficos e Mapa de Calor)
            $table->boolean('em_area_de_risco')->default(false)->comment('Vai acender de vermelho no mapa');
            $table->boolean('recebe_beneficios')->default(false)->comment('Bolsa Família, BPC, etc.');
            $table->boolean('possui_membro_com_deficiencia')->default(false);
            $table->enum('situacao_moradia', ['propria', 'alugada', 'cedida', 'ocupacao_irregular', 'situacao_de_rua'])->default('propria');

            // Histórico / Observações da Assistente Social
            $table->text('observacoes_tecnicas')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });

        // Garante que uma pessoa não seja cadastrada como Responsável Familiar 2 vezes no mesmo município
        DB::statement('CREATE UNIQUE INDEX cadastros_sociais_rf_tenant_unique ON cadastros_sociais (tenant_id, pessoa_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('cadastros_sociais');
    }
};