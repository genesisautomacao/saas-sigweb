<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoa_rendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained('pessoas')->cascadeOnDelete();
            $table->foreignId('tipo_renda_id')->nullable()->constrained('tipo_rendas')->nullOnDelete();
            $table->decimal('valor', 12, 2)->default(0);
            $table->boolean('compoe_renda_familiar')->default(true); // item 093/097
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('pessoa_rendas'); }
};
