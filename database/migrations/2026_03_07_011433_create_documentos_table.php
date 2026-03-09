<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();

            // Padrão SaaS
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->string('name'); 
            $table->string('path'); // Caminho no Storage
            $table->string('type')->nullable(); // MimeType
            $table->json('metadata')->nullable(); // Infos extras
            
            // Polimorfismo (documentable_id, documentable_type)
            $table->morphs('documentable'); 

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX documentos_tenant_id_sequential_id_unique ON documentos (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};