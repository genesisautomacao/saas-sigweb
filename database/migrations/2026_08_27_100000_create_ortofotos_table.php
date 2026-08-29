<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ortofotos por prefeitura (2026-08-27): cada tenant pode ter N camadas de
 * imagem aérea (ex.: "Ortofoto 2025", "Ortofoto 2026"), servidas como tiles
 * XYZ ({z}/{x}/{y}) de qualquer origem — R2 público, VPS (/mapas/{slug}/...)
 * ou terceiros. Aparecem como opção de mapa de fundo no web E no app.
 * FK com cascade: entra sozinha na exclusão definitiva de prefeitura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ortofotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome');                    // rótulo mostrado no seletor ("Ortofoto 2025")
            $table->string('url', 500);                // template XYZ com {z}/{x}/{y}
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ortofotos');
    }
};
