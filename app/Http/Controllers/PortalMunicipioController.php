<?php

namespace App\Http\Controllers;

use App\Models\ApiSetting;
use App\Models\Tenant;

/**
 * PT-1 — Portal de links do município (/portal/{slug}).
 * Landing pública responsiva que a prefeitura usa como destino no site oficial:
 * login do painel cidadão, cadastro, mapa público anônimo e vídeo tutorial global
 * (URL única do SaaS, cadastrada no Admin em ApiSetting name='Portal').
 */
class PortalMunicipioController extends Controller
{
    public function show(string $tenantSlug)
    {
        $tenant = Tenant::where('slug', $tenantSlug)->where('is_active', true)->first();

        abort_unless($tenant, 404, 'Município não encontrado.');

        $videoUrl = ApiSetting::where('name', 'Portal')->first()?->data['VIDEO_TUTORIAL_URL'] ?? null;

        $brandColor = (string) data_get($tenant->data, 'color', '#3b82f6');

        return view('portal-municipio', [
            'tenant' => $tenant,
            'logoUrl' => $tenant->getFilamentAvatarUrl(),
            'brandColor' => $brandColor,
            'brandColorDark' => $this->escurecer($brandColor),
            'videoUrl' => $videoUrl,
        ]);
    }

    /** Variante escura da cor da prefeitura (fim do gradiente de fundo). */
    protected function escurecer(string $hex, float $fator = 0.65): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return '#1e40af';
        }

        $rgb = array_map(
            fn ($par) => str_pad(dechex((int) floor(hexdec($par) * $fator)), 2, '0', STR_PAD_LEFT),
            str_split($hex, 2)
        );

        return '#'.implode('', $rgb);
    }
}
