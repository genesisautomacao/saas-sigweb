<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('logradouros', function (Blueprint $table) {
            $table->decimal('extensao_geo', 12, 2)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('logradouros', function (Blueprint $table) {
            $table->dropColumn('extensao_geo');
        });
    }
};
