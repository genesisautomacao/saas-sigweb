<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalização de dados: map_lat/map_lon/map_zoom no JSON tenants.data podem ter sido
 * gravados como STRING (digitados no Admin) ou número (Salvar Enquadramento do mapa).
 * O app mobile (react-native-maps) exige número — converte tudo para float/int.
 * Idempotente; strings não-numéricas são deixadas como estão.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('tenants')->get(['id', 'data']) as $tenant) {
            $data = json_decode($tenant->data ?? '{}', true);

            if (! is_array($data)) {
                continue;
            }

            $mudou = false;

            foreach (['map_lat', 'map_lon'] as $chave) {
                if (isset($data[$chave]) && is_string($data[$chave]) && is_numeric($data[$chave])) {
                    $data[$chave] = (float) $data[$chave];
                    $mudou = true;
                }
            }

            if (isset($data['map_zoom']) && is_string($data['map_zoom']) && is_numeric($data['map_zoom'])) {
                $data['map_zoom'] = (int) $data['map_zoom'];
                $mudou = true;
            }

            if ($mudou) {
                DB::table('tenants')->where('id', $tenant->id)->update([
                    'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Normalização de dados — sem reversão (string → número não precisa voltar).
    }
};
