<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('rural_propriedades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->uuid('code')->unique();
            $table->foreignId('rural_localidade_id')->nullable()->constrained('rural_localidades')->nullOnDelete();
            $table->foreignId('pessoa_id')->nullable()->constrained('pessoas')->nullOnDelete(); // O proprietário

            $table->string('nome_propriedade')->nullable();
            $table->string('codigo_incra')->nullable();
            $table->string('codigo_car')->nullable();
            $table->string('codigo_sigef')->nullable();
            $table->decimal('area_geo', 15, 2)->nullable(); 

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX rural_propriedades_tenant_seq_unique ON rural_propriedades (tenant_id, sequential_id) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE rural_propriedades ADD COLUMN geo geometry(MULTIPOLYGON, 4326)');
        DB::statement('CREATE INDEX rural_propriedades_geo_gist ON rural_propriedades USING GIST (geo)');
    }
    public function down(): void { Schema::dropIfExists('rural_propriedades'); }
};