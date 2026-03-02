<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lead_statuses', function (Blueprint $table) {
            // Adiciona a coluna 'order' zerada por padrão
            $table->integer('order')->default(0)->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('lead_statuses', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};