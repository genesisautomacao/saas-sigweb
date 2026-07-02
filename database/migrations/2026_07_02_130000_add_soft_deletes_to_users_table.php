<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft delete em users: excluir um usuário na Equipe passa a marcar deleted_at em vez de
 * apagar a linha — evita a violação de FK RESTRICT das tabelas de histórico que referenciam
 * users (processo_tramitacoes, processo_respostas, processo_anexos, pessoas, mensagens, etc.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
