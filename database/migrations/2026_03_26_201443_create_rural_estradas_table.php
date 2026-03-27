<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('rural_estradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->uuid('code')->unique();
            $table->foreignId('rural_localidade_id')->nullable()->constrained('rural_localidades')->nullOnDelete();

            $table->string('nome');
            $table->string('tipo')->default('Vicinal'); // Principal, Secundária, Vicinal
            $table->string('tipo_pavimento')->nullable(); // Terra, Cascalho, Asfalto
            $table->string('condicao_trafego')->nullable(); // Boa, Ruim, Intransitável
            $table->decimal('extensao_geo', 15, 2)->nullable(); 

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX rural_estradas_tenant_seq_unique ON rural_estradas (tenant_id, sequential_id) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE rural_estradas ADD COLUMN geo geometry(MULTILINESTRING, 4326)');
        DB::statement('CREATE INDEX rural_estradas_geo_gist ON rural_estradas USING GIST (geo)');
    }
    public function down(): void { Schema::dropIfExists('rural_estradas'); }
};