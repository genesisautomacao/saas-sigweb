<?php

namespace App\Services\Gis;

class StaticMapService
{
    private const TILE_URL  = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    private const TILE_SIZE = 256;
    private const OUT_W     = 400;
    private const OUT_H     = 300;

    /**
     * Gera um mini-mapa centrado em $lat/$lon e retorna como base64 PNG.
     * Usa GD para costurar uma grade 3×3 de tiles OSM e adicionar um marcador.
     * Retorna null se GD não estiver disponível ou se todos os tiles falharem.
     */
    public static function generate(float $lat, float $lon, int $zoom = 17): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $n  = 2 ** $zoom;
        $tx = (int) floor((($lon + 180) / 360) * $n);
        $ty = (int) floor((1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / M_PI) / 2 * $n);

        // Grade 3×3 de tiles = 768×768
        $gridPx = 3 * self::TILE_SIZE;
        $canvas = imagecreatetruecolor($gridPx, $gridPx);
        $bg     = imagecolorallocate($canvas, 230, 230, 230);
        imagefill($canvas, 0, 0, $bg);

        $ctx = stream_context_create(['http' => [
            'timeout' => 5,
            'header'  => "User-Agent: SIGWEB-StaticMap/1.0 (municipal GIS)\r\n",
        ]]);

        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                $url  = str_replace(['{z}', '{x}', '{y}'], [$zoom, $tx + $dx, $ty + $dy], self::TILE_URL);
                $data = @file_get_contents($url, false, $ctx);
                if (!$data) continue;
                $tile = @imagecreatefromstring($data);
                if (!$tile) continue;
                imagecopy($canvas, $tile, ($dx + 1) * self::TILE_SIZE, ($dy + 1) * self::TILE_SIZE, 0, 0, self::TILE_SIZE, self::TILE_SIZE);
                imagedestroy($tile);
            }
        }

        // Recorta para 400×300 centrado na grade
        $cropX = (int) (($gridPx - self::OUT_W) / 2);
        $cropY = (int) (($gridPx - self::OUT_H) / 2);
        $out   = imagecreatetruecolor(self::OUT_W, self::OUT_H);
        imagecopy($out, $canvas, 0, 0, $cropX, $cropY, self::OUT_W, self::OUT_H);
        imagedestroy($canvas);

        // Marcador no centro: círculo vermelho com ponto branco interno
        $cx    = (int) (self::OUT_W / 2);
        $cy    = (int) (self::OUT_H / 2);
        $red   = imagecolorallocate($out, 220, 38, 38);
        $white = imagecolorallocate($out, 255, 255, 255);
        $black = imagecolorallocate($out, 0, 0, 0);
        imagefilledellipse($out, $cx, $cy, 22, 22, $black); // sombra
        imagefilledellipse($out, $cx, $cy, 20, 20, $red);
        imagefilledellipse($out, $cx, $cy, 8, 8, $white);
        imagefilledellipse($out, $cx - 1, $cy - 4, 4, 4, $red); // destaque

        ob_start();
        imagepng($out);
        $png = ob_get_clean();
        imagedestroy($out);

        return 'data:image/png;base64,' . base64_encode($png);
    }
}
