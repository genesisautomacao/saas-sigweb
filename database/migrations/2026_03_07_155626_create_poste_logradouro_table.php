<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('poste_logradouro', function (Blueprint $table) {
            $table->foreignId('poste_id')->constrained('postes')->cascadeOnDelete();
            $table->foreignId('logradouro_id')->constrained('logradouros')->cascadeOnDelete();

            $table->primary(['poste_id', 'logradouro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poste_logradouro');
    }
};