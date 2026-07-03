<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('categorias_wms')) {
            Schema::create('categorias_wms', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('nome');
                $table->unsignedBigInteger('pai_id')->nullable();
                $table->integer('ordem')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('pai_id')->references('id')->on('categorias_wms')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('fontes_wms')) {
            Schema::create('fontes_wms', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('categoria_wms_id')->nullable();
                $table->string('nome');
                $table->text('url');
                $table->string('camadas'); // nome(s) da(s) camada(s)/layer(s) do serviço WMS
                $table->string('formato')->default('image/png');
                $table->unsignedTinyInteger('opacidade')->default(100); // 0-100
                $table->boolean('ativo')->default(true);
                $table->integer('ordem')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('categoria_wms_id')->references('id')->on('categorias_wms')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fontes_wms');
        Schema::dropIfExists('categorias_wms');
    }
};
