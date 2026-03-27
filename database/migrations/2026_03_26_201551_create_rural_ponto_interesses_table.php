<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('rural_pontos_interesse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->uuid('code')->unique();
            $table->foreignId('rural_localidade_id')->nullable()->constrained('rural_localidades')->nullOnDelete();

            $table->string('nome');
            $table->string('categoria'); // Educação, Religião, Saúde, etc.
            $table->text('observacoes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX rural_pontos_interesse_tenant_seq_unique ON rural_pontos_interesse (tenant_id, sequential_id) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE rural_pontos_interesse ADD COLUMN geo geometry(POINT, 4326)');
        DB::statement('CREATE INDEX rural_pontos_int_geo_gist ON rural_pontos_interesse USING GIST (geo)');
    }
    public function down(): void { Schema::dropIfExists('rural_pontos_interesse'); }
};