<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidade_imobiliarias', function (Blueprint $table) {
            // Campos fiscais promovidos do JSON dados_tributarios para colunas
            // (facilita busca, edição e relatórios). O JSON permanece como fonte
            // bruta; estas colunas são mantidas em sincronia (write-through).
            $table->string('tipo_construcao')->nullable()->after('numero_imovel');
            $table->string('descricao_classificacao')->nullable()->after('tipo_construcao');
            $table->string('face')->nullable()->after('descricao_classificacao');
            $table->decimal('fracao_ideal', 14, 2)->nullable()->after('face');

            $table->decimal('area_edificacao', 12, 2)->nullable()->after('fracao_ideal');
            $table->decimal('area_total_edificacao', 12, 2)->nullable()->after('area_edificacao');

            $table->decimal('valor_venal_lote', 14, 2)->nullable()->after('area_total_edificacao');
            $table->decimal('valor_venal_edificacao', 14, 2)->nullable()->after('valor_venal_lote');
            $table->decimal('valor_metro_terreno', 12, 2)->nullable()->after('valor_venal_edificacao');
            $table->decimal('valor_metro_edificacao', 12, 2)->nullable()->after('valor_metro_terreno');

            $table->decimal('valor_imposto_territorial', 12, 2)->nullable()->after('valor_metro_edificacao');
            $table->decimal('valor_imposto_predial', 12, 2)->nullable()->after('valor_imposto_territorial');
            $table->decimal('valor_total_imposto', 14, 2)->nullable()->after('valor_imposto_predial');
        });
    }

    public function down(): void
    {
        Schema::table('unidade_imobiliarias', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_construcao', 'descricao_classificacao', 'face', 'fracao_ideal',
                'area_edificacao', 'area_total_edificacao',
                'valor_venal_lote', 'valor_venal_edificacao',
                'valor_metro_terreno', 'valor_metro_edificacao',
                'valor_imposto_territorial', 'valor_imposto_predial', 'valor_total_imposto',
            ]);
        });
    }
};
