<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perimetros_urbanos', function (Blueprint $table) {
            $table->id();
            
            // Padrão do nosso novo SaaS
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable();
            
            $table->uuid('code')->unique();
            $table->string('name');
            $table->string('distrito')->nullable();
            $table->string('fill_color')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // 100% Fiel ao seu sistema antigo
        DB::statement('ALTER TABLE perimetros_urbanos ADD COLUMN geo geometry(MULTIPOLYGON, 4326)');
        DB::statement('CREATE INDEX perimetros_urbanos_geo_gist ON perimetros_urbanos USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX perimetros_active_unique ON perimetros_urbanos (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('perimetros_urbanos');
    }
};