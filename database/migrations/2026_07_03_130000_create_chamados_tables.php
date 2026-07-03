<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Categorias do fluxo de trabalho (pai/filho) — itens 155–159
        if (! Schema::hasTable('categorias_chamado')) {
            Schema::create('categorias_chamado', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('nome');
                $table->unsignedBigInteger('pai_id')->nullable();
                $table->string('cor')->default('#3b82f6');
                $table->string('icone')->nullable(); // path png/jpg (157)
                $table->boolean('privada')->default(false); // só fiscais (159)
                $table->integer('ordem')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('pai_id')->references('id')->on('categorias_chamado')->nullOnDelete();
            });
        }

        // 2. Fluxos de trabalho — itens 149, 154, 158
        if (! Schema::hasTable('fluxos_chamado')) {
            Schema::create('fluxos_chamado', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->unsignedBigInteger('sequential_id')->nullable();
                $table->string('nome');
                $table->foreignId('categoria_chamado_id')->nullable()->constrained('categorias_chamado')->nullOnDelete();
                $table->json('boletim')->nullable(); // questionário respondido pelo cidadão (154)
                $table->boolean('ativo')->default(true);
                $table->integer('ordem')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 3. Fases do fluxo — itens 150–153
        if (! Schema::hasTable('fases_chamado')) {
            Schema::create('fases_chamado', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('fluxo_chamado_id')->constrained('fluxos_chamado')->cascadeOnDelete();
                $table->string('nome');
                $table->string('cor')->default('#6b7280');
                $table->boolean('aviso_duracao')->default(false); // aviso de duração (150)
                $table->integer('duracao_minutos')->nullable(); // duração da fase em minutos (150)
                $table->integer('ordem')->default(0); // ordem (153)
                $table->boolean('encerramento')->default(false); // fase final (152)
                $table->json('usuarios_autorizados')->nullable(); // usuários que veem a fase (151)
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 4. Chamados (solicitações) — itens 160–174
        if (! Schema::hasTable('chamados')) {
            Schema::create('chamados', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->unsignedBigInteger('sequential_id')->nullable();
                $table->string('protocolo')->nullable();
                $table->foreignId('categoria_chamado_id')->nullable()->constrained('categorias_chamado')->nullOnDelete();
                $table->foreignId('fluxo_chamado_id')->nullable()->constrained('fluxos_chamado')->nullOnDelete();
                $table->unsignedBigInteger('fase_atual_id')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // cidadão autor (para push)
                $table->string('solicitante_nome')->nullable();
                $table->string('solicitante_telefone')->nullable();
                $table->string('solicitante_email')->nullable();
                $table->text('descricao')->nullable();
                $table->text('observacoes')->nullable();
                $table->text('anotacoes')->nullable();
                $table->json('respostas_boletim')->nullable(); // respostas do boletim (172)
                $table->json('fotos')->nullable(); // fotos da solicitação (173)
                $table->string('status')->default('aberto');
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('fase_atual_id')->references('id')->on('fases_chamado')->nullOnDelete();
            });

            DB::statement('ALTER TABLE chamados ADD COLUMN geo geometry(Point, 4326)');
            DB::statement('CREATE UNIQUE INDEX chamados_tenant_seq_unique ON chamados (tenant_id, sequential_id) WHERE deleted_at IS NULL');
        }

        // 5. Mensagens do chamado (pública/privada) — itens 169–171
        if (! Schema::hasTable('mensagens_chamado')) {
            Schema::create('mensagens_chamado', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('chamado_id')->constrained('chamados')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // remetente
                $table->text('texto');
                $table->boolean('publica')->default(true); // pública (169) vs privada interna (170)
                $table->timestamp('lido_em')->nullable();
                $table->timestamps();
            });
        }

        // 6. Histórico de mudança de fases — itens 168, 174
        if (! Schema::hasTable('historico_fases_chamado')) {
            Schema::create('historico_fases_chamado', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('chamado_id')->constrained('chamados')->cascadeOnDelete();
                $table->unsignedBigInteger('fase_id')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->foreign('fase_id')->references('id')->on('fases_chamado')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_fases_chamado');
        Schema::dropIfExists('mensagens_chamado');
        Schema::dropIfExists('chamados');
        Schema::dropIfExists('fases_chamado');
        Schema::dropIfExists('fluxos_chamado');
        Schema::dropIfExists('categorias_chamado');
    }
};
