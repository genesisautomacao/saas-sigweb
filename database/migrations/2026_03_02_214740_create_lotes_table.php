<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id')->nullable();

            $table->foreignId('quadra_id')->nullable()->constrained('quadras')->nullOnDelete();
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();

            $table->uuid('code')->unique();
            $table->string('numero_lote')->nullable();
            $table->decimal('area_geo', 12, 2)->nullable();
            $table->decimal('main_facade_length', 10, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE lotes ADD COLUMN geo geometry(MULTIPOLYGON, 4326)');
        DB::statement('CREATE INDEX lotes_geo_gist ON lotes USING GIST (geo)');
        DB::statement('CREATE UNIQUE INDEX lotes_active_unique ON lotes (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};