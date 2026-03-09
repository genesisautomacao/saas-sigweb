<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('movimentacao_itens', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('movimentacao_id')->constrained('estoque_movimentacoes')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            
            $table->decimal('quantity', 10, 2);
            $table->decimal('unitary_value', 10, 2)->nullable(); // Valor se for entrada por compra
            
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('movimentacao_itens'); }
};