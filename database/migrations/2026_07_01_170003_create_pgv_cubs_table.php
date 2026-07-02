<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pgv_cubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->string('tipologia');        // Residencial, Comercial, Galpão...
            $table->string('tipo_estrutura')->nullable(); // Alvenaria, Concreto, Madeira...
            $table->string('padrao')->nullable();          // Baixo, Normal, Alto
            $table->decimal('coeficiente', 8, 4)->default(1);
            $table->decimal('valor_m2', 14, 2)->default(0);
            $table->string('mes_referencia')->nullable(); // Ex.: 2026-06
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX pgv_cubs_tenant_seq_unique ON pgv_cubs (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void { Schema::dropIfExists('pgv_cubs'); }
};
