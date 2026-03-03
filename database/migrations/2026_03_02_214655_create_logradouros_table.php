<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('logradouros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable();

            $table->uuid('code')->unique();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        // Logradouros são linhas (MultiLineString)
        DB::statement('ALTER TABLE logradouros ADD COLUMN geo geometry(MULTILINESTRING, 4326)');
        DB::statement('CREATE INDEX logradouros_geo_gist ON logradouros USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX logradouros_active_unique ON logradouros (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('logradouros');
    }
};