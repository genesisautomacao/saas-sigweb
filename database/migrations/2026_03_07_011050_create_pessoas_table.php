<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pessoas', function (Blueprint $table) {
            $table->id();

            // Padrão SaaS
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->string('name', 150);
            $table->string('cpf', 14)->nullable();
            $table->date('birth_date')->nullable();
            $table->date('death_date')->nullable();
            $table->enum('type', ['fisica', 'juridica']);
            $table->string('cnpj', 18)->nullable();
            $table->string('trade_name', 150)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // Índices únicos por tenant (para CPF/CNPJ não nulos)
        DB::statement('CREATE UNIQUE INDEX pessoas_cpf_tenant_active_unique ON pessoas (tenant_id, cpf) WHERE deleted_at IS NULL AND cpf IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX pessoas_cnpj_tenant_active_unique ON pessoas (tenant_id, cnpj) WHERE deleted_at IS NULL AND cnpj IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX pessoas_tenant_id_sequential_id_active_unique ON pessoas (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoas');
    }
};