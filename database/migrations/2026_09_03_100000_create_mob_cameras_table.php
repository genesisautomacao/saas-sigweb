<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Câmeras de monitoramento em tempo real (Mobilidade Urbana, docs/piuma.txt
 * Onda 5): ponto no mapa + fonte do vídeo (embed/iframe, YouTube, HLS ou
 * snapshot JPEG). O SIGWEB só exibe — nunca grava imagens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mob_cameras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->string('nome', 255);
            $table->string('tipo', 20)->default('embed'); // embed | youtube | hls | imagem
            $table->text('url');
            $table->text('url_snapshot')->nullable();
            $table->string('provedor', 100)->nullable();
            $table->decimal('azimute_visada', 5, 2)->nullable(); // para onde a câmera aponta (0 = norte)
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->jsonb('dados_customizados')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE mob_cameras ADD COLUMN geo geometry(POINT, 4326)');
        DB::statement('CREATE INDEX mob_cameras_geo_gist ON mob_cameras USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX mob_cameras_tenant_sequential_unique ON mob_cameras (tenant_id, sequential_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX mob_cameras_dados_customizados_gin ON mob_cameras USING GIN (dados_customizados)');
    }

    public function down(): void
    {
        Schema::dropIfExists('mob_cameras');
    }
};
