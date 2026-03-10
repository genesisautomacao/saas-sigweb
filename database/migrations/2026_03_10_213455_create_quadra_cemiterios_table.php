<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quadras_cemiterio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable();
            
            $table->foreignId('cemiterio_id')->constrained('cemiterios')->cascadeOnDelete();
            
            $table->uuid('code')->unique();
            $table->string('name'); // Usando name ao invés de number para permitir "Quadra A"
            $table->decimal('area_geo', 12, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE quadras_cemiterio ADD COLUMN geo geometry(MULTIPOLYGON, 4326)');
        DB::statement('CREATE INDEX quadras_cemiterio_geo_gist ON quadras_cemiterio USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX quadras_cemiterio_active_unique ON quadras_cemiterio (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('quadras_cemiterio');
    }
};