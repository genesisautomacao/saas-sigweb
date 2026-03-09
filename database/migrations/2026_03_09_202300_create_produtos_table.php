<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('sequential_id');

            $table->string('name');
            $table->string('sku')->nullable(); // Código interno ou de Barras
            $table->text('description')->nullable();
            $table->string('unit'); // Unidade (UN, KG, M, CX, LT)
            
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->nullOnDelete();
            
            $table->decimal('min_stock', 10, 2)->default(0); // Estoque mínimo para alertas
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX produtos_tenant_seq_unique ON produtos (tenant_id, sequential_id) WHERE deleted_at IS NULL');
    }

    public function down(): void { Schema::dropIfExists('produtos'); }
};