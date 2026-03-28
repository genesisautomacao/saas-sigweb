<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tipo_patrimonios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            
            $table->string('name');
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // Índice único para evitar duplicidade no sequencial por prefeitura
        DB::statement('CREATE UNIQUE INDEX tipo_patrimonios_tenant_seq_unique ON tipo_patrimonios (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_patrimonios');
    }
};