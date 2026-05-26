<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edificacoes', function (Blueprint $table) {
            $table->unsignedTinyInteger('pavimento')->nullable()->after('estado_conservacao');
        });
    }

    public function down(): void
    {
        Schema::table('edificacoes', function (Blueprint $table) {
            $table->dropColumn('pavimento');
        });
    }
};
