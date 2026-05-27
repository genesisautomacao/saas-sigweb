<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('remetente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('destinatario_id')->constrained('users')->cascadeOnDelete();
            $table->text('texto');
            $table->timestamp('lido_em')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'destinatario_id', 'lido_em']);
            $table->index(['tenant_id', 'remetente_id', 'destinatario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagens');
    }
};
