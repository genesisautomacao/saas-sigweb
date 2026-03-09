<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contatos', function (Blueprint $table) {
            $table->id();

            // Padrão SaaS
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->string('contact');
            $table->enum('type', ['celular', 'telefone', 'email']);
            
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX contatos_tenant_id_sequential_id_unique ON contatos (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('contatos');
    }
};