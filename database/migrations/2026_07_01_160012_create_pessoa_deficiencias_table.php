<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoa_deficiencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->string('tipo'); // Física, Mental, Visual, Auditiva, Intelectual, Múltipla
            $table->string('cid')->nullable(); // Número do CID (item 093)
            $table->text('descricao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('pessoa_deficiencias'); }
};
