<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('rural_hidrografias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->uuid('code')->unique();
            $table->foreignId('rural_localidade_id')->nullable()->constrained('rural_localidades')->nullOnDelete();

            $table->string('nome')->nullable();
            $table->string('tipo')->default('Rio'); // Rio, Arroio, Lago, Nascente

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX rural_hidrografias_tenant_seq_unique ON rural_hidrografias (tenant_id, sequential_id) WHERE deleted_at IS NULL');
        // GEOMETRY genérico porque pode ser Ponto (Nascente), Linha (Rio) ou Polígono (Lago)
        DB::statement('ALTER TABLE rural_hidrografias ADD COLUMN geo geometry(GEOMETRY, 4326)');
        DB::statement('CREATE INDEX rural_hidrografias_geo_gist ON rural_hidrografias USING GIST (geo)');
    }
    public function down(): void { Schema::dropIfExists('rural_hidrografias'); }
};