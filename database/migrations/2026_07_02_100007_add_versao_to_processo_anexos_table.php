<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de Processos — anotação em PDF (item 222 / B15).
 * A cópia anotada é um NOVO registro em `processo_anexos`, sem sobrescrever o original:
 * - versao: número da versão anotada (original = 1)
 * - anexo_origem_id: aponta para o anexo original de onde a anotação derivou
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processo_anexos', function (Blueprint $table) {
            $table->integer('versao')->default(1)->after('tipo_anexo');
            $table->foreignId('anexo_origem_id')->nullable()->after('versao')->constrained('processo_anexos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('processo_anexos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anexo_origem_id');
            $table->dropColumn('versao');
        });
    }
};
