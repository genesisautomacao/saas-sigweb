<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // item 095 — representatividade familiar
        Schema::table('membro_familias', function (Blueprint $table) {
            $table->boolean('representante_familiar')->default(false)->after('parentesco');
        });

        // item 095 — definição social (informações sociais atribuídas à família)
        Schema::create('familia_informacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cadastro_social_id')->constrained('cadastros_sociais')->cascadeOnDelete();
            $table->foreignId('informacao_social_id')->constrained('informacao_sociais')->cascadeOnDelete();
            $table->string('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('familia_informacoes');
        Schema::table('membro_familias', function (Blueprint $table) {
            $table->dropColumn('representante_familiar');
        });
    }
};
