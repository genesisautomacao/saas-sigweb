<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Refinamento item 2: troca a flag booleana `exige_imovel` por um enum `modo_imovel`:
 *   nenhum · mapa · busca   (ver processosConceito.md §9.5)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bpmn_fluxos', function (Blueprint $table) {
            $table->string('modo_imovel')->default('mapa')->after('exige_imovel');
        });

        DB::table('bpmn_fluxos')->where('exige_imovel', true)->update(['modo_imovel' => 'mapa']);
        DB::table('bpmn_fluxos')->where('exige_imovel', false)->update(['modo_imovel' => 'nenhum']);

        Schema::table('bpmn_fluxos', function (Blueprint $table) {
            $table->dropColumn('exige_imovel');
        });
    }

    public function down(): void
    {
        Schema::table('bpmn_fluxos', function (Blueprint $table) {
            $table->boolean('exige_imovel')->default(true)->after('ativo');
        });

        DB::table('bpmn_fluxos')->where('modo_imovel', 'nenhum')->update(['exige_imovel' => false]);
        DB::table('bpmn_fluxos')->whereIn('modo_imovel', ['mapa', 'busca'])->update(['exige_imovel' => true]);

        Schema::table('bpmn_fluxos', function (Blueprint $table) {
            $table->dropColumn('modo_imovel');
        });
    }
};
