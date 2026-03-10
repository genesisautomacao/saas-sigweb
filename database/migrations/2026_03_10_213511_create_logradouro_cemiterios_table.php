<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('logradouros_cemiterio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable();
            
            $table->foreignId('cemiterio_id')->constrained('cemiterios')->cascadeOnDelete();
            
            $table->uuid('code')->unique();
            $table->string('name');

            $table->timestamps();
            $table->softDeletes();
        });

        // Logradouros são Linhas!
        DB::statement('ALTER TABLE logradouros_cemiterio ADD COLUMN geo geometry(MULTILINESTRING, 4326)');
        DB::statement('CREATE INDEX logradouros_cemiterio_geo_gist ON logradouros_cemiterio USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX logradouros_cemiterio_active_unique ON logradouros_cemiterio (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('logradouros_cemiterio');
    }
};