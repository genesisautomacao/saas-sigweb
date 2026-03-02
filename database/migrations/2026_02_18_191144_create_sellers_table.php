<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            
            // Relacionamento 1-para-1 com o Usuário (autenticação)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Campos específicos do negócio para o vendedor
            $table->decimal('commission_rate', 5, 2)->nullable()->comment('Porcentagem de comissão');
            $table->string('region')->nullable()->comment('Região de atuação, ex: Zona ABC');
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};