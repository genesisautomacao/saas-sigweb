<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bairros', function (Blueprint $table) {
            $table->id();

            // Padrão SaaS
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable();

            $table->uuid('code')->unique();
            $table->string('name');
            $table->string('setor')->nullable()->comment('Código do setor (CTM)');

            $table->timestamps();
            $table->softDeletes();
        });

        // 100% Fiel ao seu sistema antigo
        DB::statement('ALTER TABLE bairros ADD COLUMN geo geometry(MULTIPOLYGON, 4326)');
        DB::statement('CREATE INDEX bairros_geo_gist ON bairros USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX bairros_active_unique ON bairros (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('bairros');
    }
};