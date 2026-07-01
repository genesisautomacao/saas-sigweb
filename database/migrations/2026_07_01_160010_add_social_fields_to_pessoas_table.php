<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pessoas', function (Blueprint $table) {
            // Campos da Pessoa - Social (item 092)
            $table->string('rg')->nullable()->after('cpf');
            $table->string('ctps')->nullable()->after('rg');
            $table->string('pis')->nullable()->after('ctps');
            $table->string('nis')->nullable()->after('pis');
            $table->string('certidao_nascimento')->nullable()->after('nis');
            $table->string('telefone')->nullable()->after('certidao_nascimento');
            $table->enum('estado_civil', ['solteiro', 'casado', 'divorciado', 'viuvo', 'uniao_estavel', 'separado'])->nullable()->after('telefone');
            $table->enum('sexo', ['masculino', 'feminino', 'outro'])->nullable()->after('estado_civil');
            $table->foreignId('pai_id')->nullable()->after('sexo')->constrained('pessoas')->nullOnDelete();
            $table->foreignId('mae_id')->nullable()->after('pai_id')->constrained('pessoas')->nullOnDelete();
            $table->foreignId('conjuge_id')->nullable()->after('mae_id')->constrained('pessoas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pessoas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pai_id');
            $table->dropConstrainedForeignId('mae_id');
            $table->dropConstrainedForeignId('conjuge_id');
            $table->dropColumn(['rg', 'ctps', 'pis', 'nis', 'certidao_nascimento', 'telefone', 'estado_civil', 'sexo']);
        });
    }
};
