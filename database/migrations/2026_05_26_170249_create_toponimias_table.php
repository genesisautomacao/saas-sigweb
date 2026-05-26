<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toponimias', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('sequential_id')->nullable();
            $table->string('texto');
            $table->decimal('lat', 10, 7);
            $table->decimal('lon', 11, 7);
            $table->json('estilo')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
        });

        DB::statement('ALTER TABLE toponimias ADD COLUMN IF NOT EXISTS geo geometry(Point,4326)');
        DB::statement('CREATE INDEX IF NOT EXISTS toponimias_geo_gist ON toponimias USING GIST(geo)');
    }

    public function down(): void
    {
        Schema::dropIfExists('toponimias');
    }
};
