<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R80-1 — Chamados pelo mapa público (módulo `chamados_mapa`): formulário aberto
 * (sem login) no mapa público; a prefeitura acompanha no grupo "Chamados pelo Mapa".
 * Separado do App de Chamados (`chamados`, cidadão logado + categorias/fluxos/fases).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chamados_mapa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->string('protocolo', 20); // {ano}.{sequencial} — único por prefeitura

            // Quem abriu (cidadão anônimo)
            $table->string('nome', 150);
            $table->string('celular', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('cpf', 11)->nullable(); // só dígitos; validado pelos DVs quando informado

            // O chamado
            $table->string('assunto', 20); // solicitacao | sugestao | reclamacao
            $table->string('titulo', 150);
            $table->text('descricao');
            $table->string('foto')->nullable();
            $table->string('foto_disco', 20)->nullable(); // midia (R2 privado) | local

            // Atendimento
            $table->string('situacao', 20)->default('pendente'); // pendente | em_atendimento | atendido | irrelevante
            $table->text('resposta')->nullable();            // vai no e-mail ao cidadão
            $table->text('observacao_interna')->nullable();  // só a equipe vê
            $table->timestamp('situacao_alterada_em')->nullable();
            $table->foreignId('atendido_por_id')->nullable()->constrained('users')->nullOnDelete();

            // Rastro do envio (anti-spam / auditoria)
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'situacao']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'cpf']); // futura consulta pública de protocolos
        });

        DB::statement('ALTER TABLE chamados_mapa ADD COLUMN geo geometry(POINT, 4326)');
        DB::statement('CREATE INDEX chamados_mapa_geo_gist ON chamados_mapa USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX chamados_mapa_tenant_protocolo_unique ON chamados_mapa (tenant_id, protocolo)');
        DB::statement('CREATE UNIQUE INDEX chamados_mapa_tenant_sequential_unique ON chamados_mapa (tenant_id, sequential_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('chamados_mapa');
    }
};
