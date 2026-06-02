<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('zonas', function (Blueprint $table) {
            $table->decimal('area_geo', 12, 2)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('zonas', function (Blueprint $table) {
            $table->dropColumn('area_geo');
        });
    }
};
