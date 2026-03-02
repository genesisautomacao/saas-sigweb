<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_statuses', function (Blueprint $table) {
            $table->id();
            // Chave estrangeira para o isolamento do Tenant
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete(); 
            
            $table->string('name');
            $table->string('color')->nullable(); // Ex: 'danger', 'success', '#ff0000'
            $table->boolean('is_default')->default(false);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_statuses');
    }
};