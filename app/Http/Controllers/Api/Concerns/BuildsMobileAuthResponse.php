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
                'map_lat' => $data['map_lat'] ?? null,
                'map_lon' => $data['map_lon'] ?? null,
                'map_zoom' => $data['map_zoom'] ?? null,
            ],
            'layers' => $layers,
        ];
    }
}
