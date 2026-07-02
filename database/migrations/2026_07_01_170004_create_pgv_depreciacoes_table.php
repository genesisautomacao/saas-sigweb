<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pgv_depreciacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->string('estado_conservacao'); // Bom, Regular, Ruim, Péssimo
            $table->integer('idade_de')->default(0);
            $table->integer('idade_ate')->default(0);
            $table->decimal('coeficiente', 8, 4)->default(1); // fator multiplicador (item 229)
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX pgv_depreciacoes_tenant_seq_unique ON pgv_depreciacoes (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void { Schema::dropIfExists('pgv_depreciacoes'); }
};
