<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jazigos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable();
            
            $table->foreignId('quadra_cemiterio_id')->constrained('quadras_cemiterio')->cascadeOnDelete();
            $table->foreignId('proprietario_id')->nullable()->constrained('pessoas')->nullOnDelete();
            
            $table->uuid('code')->unique();
            $table->string('codigo'); // Ex: J-15, A-02
            $table->string('tipo')->nullable(); // Gaveta, Chão, Mausoléu
            $table->string('status')->default('disponivel'); // disponivel, ocupado, manutencao
            $table->decimal('area_geo', 10, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE jazigos ADD COLUMN geo geometry(MULTIPOLYGON, 4326)');
        DB::statement('CREATE INDEX jazigos_geo_gist ON jazigos USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX jazigos_active_unique ON jazigos (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('jazigos');
    }
};