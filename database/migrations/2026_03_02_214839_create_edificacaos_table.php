<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable();

            $table->foreignId('lote_id')->nullable()->constrained('lotes')->cascadeOnDelete();

            $table->uuid('code')->unique();
            $table->string('tipo')->nullable();
            $table->string('tp_construcao')->nullable();
            $table->string('caracteristica_construcao')->nullable();
            $table->string('estado_conservacao')->nullable();
            $table->decimal('area_geo', 12, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE edificacoes ADD COLUMN geo geometry(MULTIPOLYGON, 4326)');
        DB::statement('CREATE INDEX edificacoes_geo_gist ON edificacoes USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX edificacoes_active_unique ON edificacoes (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('edificacoes');
    }
};