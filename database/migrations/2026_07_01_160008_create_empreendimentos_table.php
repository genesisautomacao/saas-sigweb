<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empreendimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');
            $table->string('name'); // Ex.: Residencial Minha Casa Minha Vida
            $table->text('descricao')->nullable();
            $table->string('endereco')->nullable();
            $table->integer('num_unidades')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Ponto geográfico (moradia de benefício) — usado no Painel Social (item 098)
        DB::statement('ALTER TABLE empreendimentos ADD COLUMN geo geometry(Point, 4326)');
        DB::statement('CREATE UNIQUE INDEX empreendimentos_tenant_seq_unique ON empreendimentos (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void { Schema::dropIfExists('empreendimentos'); }
};
