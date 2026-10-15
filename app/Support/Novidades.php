<?php

namespace App\Support;

/**
 * Páginas de novidades das releases (`public/novidades/release-{N}.html`), servidas como
 * arquivo estático em /novidades/... e listadas no /admin → Novidades.
 *
 * Cada página declara no <head>:
 *   <meta name="sigweb-release" content="84">
 *   <meta name="sigweb-data" content="2026-10-15">
 *   <meta name="description" content="…resumo de uma linha…">
 * Página nova = só criar o arquivo; nada a cadastrar.
 */
final class Novidades
{
    public const PASTA = 'novidades';

    /** @return list<array{release:int, data:?string, titulo:string, resumo:?string, url:string, arquivo:string}> */
    public static function lista(): array
    {
        $itens = [];

        foreach (glob(public_path(self::PASTA.'/release-*.html')) ?: [] as $caminho) {
            $cabeca = (string) file_get_contents($caminho, false, null, 0, 8192);
            $meta = fn (string $nome) => preg_match('/<meta\s+name="'.preg_quote($nome, '/').'"\s+content="([^"]*)"/i', $cabeca, $m)
                ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;

            $release = (int) ($meta('sigweb-release') ?? preg_replace('/\D/', '', basename($caminho)));
            $titulo = preg_match('/<title>(.*?)<\/title>/is', $cabeca, $t) ? trim(html_entity_decode($t[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : 'Release '.$release;

            $itens[] = [
                'release' => $release,
                'data' => $meta('sigweb-data'),
                'titulo' => $titulo,
                'resumo' => $meta('description'),
                'url' => asset(self::PASTA.'/'.basename($caminho)),
                'arquivo' => basename($caminho),
            ];
        }

        usort($itens, fn ($a, $b) => $b['release'] <=> $a['release']);

        return $itens;
    }
}
