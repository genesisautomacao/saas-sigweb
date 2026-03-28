<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('patrimonio_publicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->uuid('code')->unique();
            
            $table->foreignId('tipo_patrimonio_id')->nullable()->constrained('tipo_patrimonios')->nullOnDelete();

            $table->string('name');
            $table->string('address')->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX patrimonio_publicos_tenant_seq_unique ON patrimonio_publicos (tenant_id, sequential_id) WHERE deleted_at IS NULL');
        
        // GEOMETRY genérico: aceita Pontos, Linhas ou Polígonos
        DB::statement('ALTER TABLE patrimonio_publicos ADD COLUMN geo geometry(GEOMETRY, 4326)');
        DB::statement('CREATE INDEX patrimonio_publicos_geo_gist ON patrimonio_publicos USING GIST (geo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('patrimonio_publicos');
    }
};