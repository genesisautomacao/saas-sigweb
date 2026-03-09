<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('enderecos', function (Blueprint $table) {
            $table->id();

            // Padrão SaaS
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            
            // Dados Completos de Endereço (ViaCEP)
            $table->string('cep', 10)->nullable();
            $table->string('address')->nullable();      // Logradouro (Rua, Av)
            $table->string('number', 20)->nullable();   // Número
            $table->string('complement')->nullable();   // Complemento
            $table->string('neighborhood')->nullable(); // Bairro
            $table->string('city')->nullable();         // Cidade
            $table->string('state', 2)->nullable();     // UF

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX enderecos_tenant_id_sequential_id_unique ON enderecos (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('enderecos');
    }
};