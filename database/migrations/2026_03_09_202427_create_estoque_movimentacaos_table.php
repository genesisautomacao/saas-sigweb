<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('estoque_movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->enum('type', ['entrada', 'saida', 'transferencia']);
            
            $table->foreignId('user_id')->constrained('users'); // Quem fez a ação
            
            // De Onde e Para Onde (Podem ser nulos dependendo do tipo da movimentação)
            $table->foreignId('origem_id')->nullable()->constrained('locais_estoque');
            $table->foreignId('destino_id')->nullable()->constrained('locais_estoque');

            // Polimorfismo: "Isso foi gasto na OS 10? Foi comprado na NF 50?"
            $table->nullableMorphs('referencia'); // Cria referencia_type e referencia_id

            $table->text('observacao')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX est_mov_tenant_seq_unique ON estoque_movimentacoes (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void { Schema::dropIfExists('estoque_movimentacoes'); }
};