<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprime com gzip as respostas JSON pesadas do mapa (GeoJSON de camadas,
 * consultas avançadas). Um município de 6.000 lotes cai de ~3,7 MB para
 * ~0,5 MB na rede — decisivo em VPS/conexões lentas (2026-08-06).
 *
 * Aplicado por rota (routes/web.php). Se o nginx/Apache já comprimir,
 * o guard de Content-Encoding evita dupla compressão.
 */
class ComprimirJsonMapa
{
    /** Abaixo disso não vale o CPU da compressão. */
    protected const TAMANHO_MINIMO = 51200; // 50 KB

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! str_contains((string) $request->header('Accept-Encoding'), 'gzip')) {
            return $response;
        }

        if ($response->headers->has('Content-Encoding')) {
            return $response; // já comprimida (proxy/servidor web)
        }

        $conteudo = $response->getContent();

        if (! is_string($conteudo) || strlen($conteudo) < self::TAMANHO_MINIMO) {
            return $response; // stream/arquivo ou payload pequeno
        }

        $comprimido = gzencode($conteudo, 5);

        if ($comprimido === false) {
            return $response;
        }

        $response->setContent($comprimido);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', 'Accept-Encoding');
        $response->headers->remove('Content-Length');

        return $response;
    }
}
