<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cadastrador_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lon', 10, 7);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('user_id'); // upsert — um registro por usuário
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cadastrador_locations');
    }
};
