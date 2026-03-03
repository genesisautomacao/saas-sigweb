<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quadras', function (Blueprint $table) {
            $table->id();

            // Padrão SaaS
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable();

            // Hierarquia
            $table->foreignId('perimetro_id')->nullable()->constrained('perimetros_urbanos')->nullOnDelete();
            $table->foreignId('bairro_id')->nullable()->constrained('bairros')->nullOnDelete();
            $table->foreignId('loteamento_id')->nullable()->constrained('loteamentos')->nullOnDelete();

            // Dados
            $table->string('name'); // Substitui 'number'. Aceita "Quadra A", "Q-01", "15", etc.
            $table->uuid('code')->unique();

            $table->timestamps();
            $table->softDeletes();
        });

        // Geometria
        DB::statement('ALTER TABLE quadras ADD COLUMN geo geometry(MULTIPOLYGON, 4326)');
        DB::statement('CREATE INDEX quadras_geo_gist ON quadras USING GIST (geo)');

        // Unicidade
        DB::statement('CREATE UNIQUE INDEX quadras_active_unique ON quadras (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('quadras');
    }
};