<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * R67-4 — o pull do app passa a filtrar lotes por quadra (região do cadastrador).
 * No PostgreSQL a FK não cria índice: sem ele o whereIn('quadra_id', …) faz seq scan
 * (e DELETEs em quadras também sofrem). Mesmo molde da migration de índices de FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS lotes_quadra_id_index ON lotes (quadra_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS lotes_quadra_id_index');
    }
};
