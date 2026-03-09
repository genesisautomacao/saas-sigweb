<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('estoques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('local_estoque_id')->constrained('locais_estoque')->cascadeOnDelete();
            
            $table->decimal('quantity', 10, 2)->default(0);

            $table->timestamps();
        });

        // 🛑 TRAVA MESTRA: Um produto só pode ter uma única linha de saldo em cada local!
        Schema::table('estoques', function (Blueprint $table) {
            $table->unique(['tenant_id', 'produto_id', 'local_estoque_id'], 'estoque_unico_local');
        });
    }

    public function down(): void { Schema::dropIfExists('estoques'); }
};