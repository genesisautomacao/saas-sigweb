<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;

/**
 * Monta o payload padrão de autenticação do mobile (token + user + tenant + layers).
 *
 * Usado pelo login por e-mail/senha (AuthController) e pelo cadastro/login social do
 * cidadão (CidadaoAuthController), para que TODAS as portas de entrada devolvam
 * exatamente a mesma estrutura — o app trata a resposta de forma única.
 */
trait BuildsMobileAuthResponse
{
    protected function mobileAuthResponse(User $user): array
    {
        $token = $user->createToken('app-mobile')->plainTextToken;

        $tenant = $user->tenants()->first();
        $data = $tenant?->data ?? [];
        $modules = $tenant?->modules ?? [];

        $layerMap = [
            'arborizacao' => ['arvores'],
            'iluminacao' => ['postes'],
            'cemiterio' => ['cemiterios', 'jazigos'],
        ];

        $layers = ['lotes', 'quadras', 'logradouros', 'bairros'];
        foreach ($layerMap as $mod => $camadas) {
            if (in_array($mod, $modules)) {
                $layers = array_merge($layers, $camadas);
            }
        }

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'tenant' => [
                'id' => $tenant?->id,
                'name' => $tenant?->name,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                // Sempre NÚMERO no payload: em tenant.data (JSON) esses valores podem ter sido
                // gravados como string (digitados no Admin) ou número (Salvar Enquadramento do
                // mapa) — o app (react-native-maps) exige número.
                'map_lat' => isset($data['map_lat']) && is_numeric($data['map_lat']) ? (float) $data['map_lat'] : null,
                'map_lon' => isset($data['map_lon']) && is_numeric($data['map_lon']) ? (float) $data['map_lon'] : null,
                'map_zoom' => isset($data['map_zoom']) && is_numeric($data['map_zoom']) ? (int) $data['map_zoom'] : null,
            ],
            'layers' => $layers,
        ];
    }
}
