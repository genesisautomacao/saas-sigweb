<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();

            // Padrão SaaS
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable();

            // Relacionamento (Uma zona pode pertencer a um perímetro)
            $table->foreignId('perimetro_id')->nullable()->constrained('perimetros_urbanos')->nullOnDelete();

            // Dados
            $table->string('name');
            $table->string('sigla')->nullable();
            $table->string('rgb')->nullable();
            $table->uuid('code')->unique();

            $table->timestamps();
            $table->softDeletes();
        });

        // Geometria
        DB::statement('ALTER TABLE zonas ADD COLUMN geo geometry(MULTIPOLYGON, 4326)');
        DB::statement('CREATE INDEX zonas_geo_gist ON zonas USING GIST (geo)');

        // Unicidade
        DB::statement('CREATE UNIQUE INDEX zonas_active_unique ON zonas (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas');
    }
};