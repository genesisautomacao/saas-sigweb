<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cemiterios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable();
            
            $table->uuid('code')->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->decimal('area_geo', 12, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE cemiterios ADD COLUMN geo geometry(MULTIPOLYGON, 4326)');
        DB::statement('CREATE INDEX cemiterios_geo_gist ON cemiterios USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX cemiterios_active_unique ON cemiterios (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('cemiterios');
    }
};