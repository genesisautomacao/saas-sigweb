<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pgv_polos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->string('name'); // Ex.: Praça Central, Avenida Principal
            $table->timestamps();
            $table->softDeletes();
        });

        // Pólo valorizante (item 227) — ponto de atração de valor
        DB::statement('ALTER TABLE pgv_polos ADD COLUMN geo geometry(Point, 4326)');
        DB::statement('CREATE UNIQUE INDEX pgv_polos_tenant_seq_unique ON pgv_polos (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void { Schema::dropIfExists('pgv_polos'); }
};
