<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('unidade_imobiliarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable();

            $table->foreignId('lote_id')->nullable()->constrained('lotes')->nullOnDelete();

            // Faremos a tabela de pessoas depois, deixe o id solto por enquanto
            $table->unsignedBigInteger('proprietario_id')->nullable();

            $table->uuid('code')->unique();
            $table->string('codigo_imovel_tributario')->nullable();
            $table->string('inscricao_imobiliaria')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // ATENÇÃO: É um ponto (POINT)
        DB::statement('ALTER TABLE unidade_imobiliarias ADD COLUMN geo geometry(POINT, 4326)');
        DB::statement('CREATE INDEX uni_imo_geo_gist ON unidade_imobiliarias USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX uni_imo_active_unique ON unidade_imobiliarias (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('unidade_imobiliarias');
    }
};