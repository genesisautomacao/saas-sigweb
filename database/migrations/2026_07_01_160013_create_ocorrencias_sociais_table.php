<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocorrencias_sociais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // Polimórfica: usada por Pessoa (item 093) e Família/CadastroSocial (item 095)
            $table->morphs('ocorrenciavel');
            $table->date('data')->nullable();
            $table->string('tipo'); // alteracao_cadastral, atendimento, encaminhamento, denuncia...
            $table->text('descricao')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('ocorrencias_sociais'); }
};
