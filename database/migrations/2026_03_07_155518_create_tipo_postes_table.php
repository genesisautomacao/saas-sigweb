<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tipos_poste', function (Blueprint $table) {
            $table->id();

            // Padrão SaaS
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->string('name'); 

            $table->timestamps();
            $table->softDeletes();
        });

        // Índices únicos condicionais para evitar duplicidade dentro do mesmo Tenant
        DB::statement('CREATE UNIQUE INDEX tipos_poste_tenant_id_seq_id_unique ON tipos_poste (tenant_id, sequential_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX tipos_poste_tenant_id_name_unique ON tipos_poste (tenant_id, name) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_poste');
    }
};