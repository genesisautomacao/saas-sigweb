<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tamanho do tile da pirâmide (256 ou 512 px) — 2026-09-01.
 *
 * Não é preferência do usuário: é um FATO sobre a pirâmide (gdal2tiles --tilesize).
 * Uma pirâmide 256 desenhada como 512 aparece em escala errada. Padrão do SIGWEB
 * passa a ser 512 (¼ das requisições na mesma resolução); 256 fica para fontes
 * de terceiros (GeoServer/ArcGIS/Google usam 256 por convenção).
 *
 * Default do BANCO = 256 (linhas existentes/legadas seguem a convenção XYZ universal);
 * default do FORMULÁRIO = 512 (padrão de geração do projeto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ortofotos', function (Blueprint $table) {
            $table->unsignedSmallInteger('tile_size')->default(256)->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('ortofotos', function (Blueprint $table) {
            $table->dropColumn('tile_size');
        });
    }
};
