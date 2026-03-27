<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('rural_pontes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->uuid('code')->unique();
            $table->foreignId('rural_localidade_id')->nullable()->constrained('rural_localidades')->nullOnDelete();
            $table->foreignId('rural_estrada_id')->nullable()->constrained('rural_estradas')->nullOnDelete(); // A ponte fica numa estrada

            $table->string('nome_referencia')->nullable();
            $table->string('material_construcao')->nullable(); // Madeira, Concreto, Mista
            $table->integer('capacidade_carga_toneladas')->nullable();
            $table->string('estado_conservacao')->nullable(); 

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX rural_pontes_tenant_seq_unique ON rural_pontes (tenant_id, sequential_id) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE rural_pontes ADD COLUMN geo geometry(POINT, 4326)');
        DB::statement('CREATE INDEX rural_pontes_geo_gist ON rural_pontes USING GIST (geo)');
    }
    public function down(): void { Schema::dropIfExists('rural_pontes'); }
};