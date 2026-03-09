<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('postes', function (Blueprint $table) {
            $table->id();

            // Padrão SaaS
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->uuid('code')->unique();
            $table->string('address')->nullable();
            
            // Relacionamento com TipoPoste (Restringe a exclusão se houver postes usando)
            $table->foreignId('tipo_poste_id')->constrained('tipos_poste')->restrictOnDelete();

            // Campos Específicos
            $table->decimal('height', 8, 2)->nullable(); 
            $table->date('installation_date')->nullable(); 
            $table->string('structural_condition')->nullable(); 
            $table->string('luminaire_type')->nullable(); 
            $table->string('lamp_power')->nullable(); 
            $table->decimal('luminaire_height', 8, 2)->nullable(); 
            $table->integer('lamp_quantity')->nullable(); 
            $table->text('observations')->nullable(); 

            $table->timestamps();
            $table->softDeletes();
        });

        // Índice único
        DB::statement('CREATE UNIQUE INDEX postes_tenant_id_sequential_id_unique ON postes (tenant_id, sequential_id) WHERE deleted_at IS NULL');

        // Geometria (PONTO) e Índice Espacial (GIST)
        DB::statement('ALTER TABLE postes ADD COLUMN geo geometry(POINT, 4326)');
        DB::statement('CREATE INDEX postes_geo_gist ON postes USING GIST (geo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('postes');
    }
};