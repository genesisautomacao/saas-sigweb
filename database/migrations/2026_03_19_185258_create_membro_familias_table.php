<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membro_familias', function (Blueprint $table) {
            $table->id();
            
            // Padrão SaaS
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Os Elos (De qual família e quem é a pessoa)
            $table->foreignId('cadastro_social_id')->constrained('cadastros_sociais')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            
            // O Vínculo
            $table->enum('parentesco', [
                'conjuge', 
                'filho_a', 
                'enteado_a', 
                'pai_mae', 
                'avo_a', 
                'neto_a', 
                'outro'
            ])->default('outro');

            $table->timestamps();
        });

        // 🛑 A TRAVA MÁGICA NO BANCO: 
        // Garante que uma pessoa só pode existir UMA ÚNICA VEZ como membro de família na prefeitura inteira
        DB::statement('CREATE UNIQUE INDEX membro_familias_pessoa_tenant_unique ON membro_familias (tenant_id, pessoa_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('membro_familias');
    }
};