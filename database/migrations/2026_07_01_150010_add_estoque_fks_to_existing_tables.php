<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marca Comercial pertence a um Fabricante
        Schema::table('marcas', function (Blueprint $table) {
            $table->foreignId('fabricante_id')->nullable()->after('name')->constrained('fabricantes')->nullOnDelete();
        });

        // Local de Estoque pertence a um Estabelecimento
        Schema::table('locais_estoque', function (Blueprint $table) {
            $table->foreignId('estabelecimento_id')->nullable()->after('name')->constrained('estabelecimentos')->nullOnDelete();
        });

        // Produto ganha Família, Unidade de Medida de apresentação e Embalagem
        Schema::table('produtos', function (Blueprint $table) {
            $table->foreignId('familia_produto_id')->nullable()->after('marca_id')->constrained('familia_produtos')->nullOnDelete();
            $table->foreignId('unidade_medida_id')->nullable()->after('familia_produto_id')->constrained('unidade_medidas')->nullOnDelete();
            $table->foreignId('embalagem_id')->nullable()->after('unidade_medida_id')->constrained('embalagens')->nullOnDelete();
        });

        // Saldo por local + tipo de estoque + lote/série (itens 055/058)
        Schema::table('estoques', function (Blueprint $table) {
            $table->foreignId('tipo_estoque_id')->nullable()->after('local_estoque_id')->constrained('tipo_estoques')->nullOnDelete();
            $table->foreignId('lote_estoque_id')->nullable()->after('tipo_estoque_id')->constrained('lote_estoques')->nullOnDelete();
        });

        // Movimentação configurável por Operação Interna + tipo de estoque origem/destino
        Schema::table('estoque_movimentacoes', function (Blueprint $table) {
            $table->foreignId('operacao_interna_id')->nullable()->after('type')->constrained('operacao_internas')->nullOnDelete();
            $table->foreignId('tipo_estoque_origem_id')->nullable()->after('origem_id')->constrained('tipo_estoques')->nullOnDelete();
            $table->foreignId('tipo_estoque_destino_id')->nullable()->after('destino_id')->constrained('tipo_estoques')->nullOnDelete();
        });

        // Item de movimentação referencia um lote/série
        Schema::table('movimentacao_itens', function (Blueprint $table) {
            $table->foreignId('lote_estoque_id')->nullable()->after('produto_id')->constrained('lote_estoques')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fabricante_id');
        });
        Schema::table('locais_estoque', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estabelecimento_id');
        });
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('familia_produto_id');
            $table->dropConstrainedForeignId('unidade_medida_id');
            $table->dropConstrainedForeignId('embalagem_id');
        });
        Schema::table('estoques', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_estoque_id');
            $table->dropConstrainedForeignId('lote_estoque_id');
        });
        Schema::table('estoque_movimentacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operacao_interna_id');
            $table->dropConstrainedForeignId('tipo_estoque_origem_id');
            $table->dropConstrainedForeignId('tipo_estoque_destino_id');
        });
        Schema::table('movimentacao_itens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lote_estoque_id');
        });
    }
};
