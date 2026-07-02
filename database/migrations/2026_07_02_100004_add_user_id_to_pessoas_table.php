<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Motor de Processos — vínculo User ↔ Pessoa (decisão #1, processosConceito.md §9.1).
 * A Pessoa é o registro canônico (tenant-scoped, com cpf/telefone); o User é a conta.
 * O elo mora em pessoas.user_id (nullable). Um User = no máx. uma Pessoa por tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pessoas', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX pessoas_tenant_user_unique ON pessoas (tenant_id, user_id) WHERE user_id IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pessoas_tenant_user_unique');

        Schema::table('pessoas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
