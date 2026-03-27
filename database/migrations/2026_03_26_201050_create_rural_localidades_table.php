<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rural_localidades', function (Blueprint $table) {
            $table->id();

            // Padrão SaaS do SIGWEB
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->uuid('code')->unique();
            
            // Dados da Localidade/Distrito
            $table->string('nome');
            $table->string('tipo')->default('Localidade'); // Ex: Distrito, Localidade, Povoado
            
            // Opcional: Para guardar a área calculada via PostGIS
            $table->decimal('area_geo', 15, 2)->nullable(); 

            $table->timestamps();
            $table->softDeletes();
        });

        // Índice único para evitar duplicidade no sequencial por prefeitura
        DB::statement('CREATE UNIQUE INDEX rural_localidades_tenant_seq_unique ON rural_localidades (tenant_id, sequential_id) WHERE deleted_at IS NULL');

        // Geometria (POLYGON/MULTIPOLYGON) e Índice Espacial (GIST)
        DB::statement('ALTER TABLE rural_localidades ADD COLUMN geo geometry(MULTIPOLYGON, 4326)');
        DB::statement('CREATE INDEX rural_localidades_geo_gist ON rural_localidades USING GIST (geo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rural_localidades');
    }
};