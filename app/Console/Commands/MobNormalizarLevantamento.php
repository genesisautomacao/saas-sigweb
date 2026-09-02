<?php

namespace App\Console\Commands;

use Database\Seeders\MobilidadeSeeder;
use Illuminate\Console\Command;

/**
 * Mobilidade Urbana (docs/piuma.txt, Onda 1) — transforma os JSONs CRUS do
 * levantamento de campo nos 6 GeoJSONs NORMALIZADOS que o "Importar Mapa (GIS)"
 * entende (camadas MobTrecho, MobSinalizacao, MobPontoInteresse, MobEixo,
 * MobZona, MobFluxo) + cópia de bairros.json (camada Bairro existente).
 *
 * Normalizações (decisões 6.x do piuma.txt):
 *  - sinalização: de/para por palavra-chave name → tipo do CATÁLOGO (6.1);
 *    texto cru preservado em descricao_original; não classificado → "A Classificar";
 *  - rodovias DER-ES: só o que intersecta o bbox do município (6.2/P3);
 *  - fluxos O/D: dedup por geometria idêntica + destino (P4);
 *  - trechos: renomeia o typo "esuqerdo", casa valores com as opções do kit;
 *  - merges com renumeração de id (sequential_id única por camada).
 *
 * Uso: php artisan mob:normalizar-levantamento "D:\...\json_concluido_30-08-26" --saida="C:\tmp\piuma-normalizado"
 */
class MobNormalizarLevantamento extends Command
{
    protected $signature = 'mob:normalizar-levantamento
                            {pasta : Pasta com os JSONs crus do levantamento}
                            {--saida= : Pasta de saída (padrão: <pasta>/normalizado)}';

    protected $description = 'Normaliza os GeoJSONs do levantamento de mobilidade (Piúma) para importação';

    /** [arquivo, categoria] dos POIs. */
    private const POIS = [
        ['comercio_e_servicos.json', 'comercio_servicos'],
        ['educacao.json', 'educacao'],
        ['saude.json', 'saude'],
        ['religioso.json', 'religioso'],
        ['turismo_lazer_esporte.json', 'turismo_lazer_esporte'],
        ['industrias.json', 'industria'],
        ['pontos_transporte_cargas.json', 'posto_combustivel'],
    ];

    /**
     * De/para da sinalização: [regex sobre o texto normalizado (minúsculo, sem
     * acento, sem timestamp), nome no catálogo]. PRIMEIRA regra que casar vence
     * — a ordem importa (específico antes do genérico).
     */
    private const REGRAS_SINALIZACAO = [
        // Estacionamento regulamentado — subtipos primeiro
        ['/idoso|60\+/', 'Vaga de Idoso'],
        ['/pcd|deficien|necessidade especial|credencial/', 'Vaga PCD'],
        ['/\bmoto\b/', 'Vaga de Moto'],
        ['/carga/', 'Carga e Descarga'],
        ['/curta dura/', 'Estacionamento de Curta Duração'],
        ['/taxi/', 'Táxi'],
        ['/oficiais/', 'Veículos Oficiais'],
        ['/ponto de onibus|ponto de parada|identificacao ponto/', 'Identificação de Ponto de Ônibus'],
        // Proibições
        ['/proibido (parar e estacionar|estacionar e parar)/', 'Proibido Parar e Estacionar'],
        ['/(proibido|proibindo|proibida) estacionar|nao pare/', 'Proibido Estacionar'],
        ['/ultrapassar/', 'Proibido Ultrapassar'],
        ['/(proibido|permitido).*(virar|para|a) esquerda|vire a esquerda/', 'Proibido Virar à Esquerda'],
        ['/proibido.*(virar|a) direita/', 'Proibido Virar à Direita'],
        ['/sentido proibido/', 'Sentido Proibido'],
        ['/proibido (a )?seguir/', 'Sentido Proibido'],
        // Advertência / geometria da via
        ['/rotator|inters?e(cao|ccao|ç).*(circulo)|a-?12/', 'Interseção em Círculo (Rotatória)'],
        ['/curva/', 'Curva Acentuada'],
        ['/lombada|quebra.?mola/', 'Lombada à Frente'],
        ['/declive/', 'Declive Acentuado'],
        ['/animais/', 'Animais Silvestres'],
        ['/fiscalizacao/', 'Fiscalização Eletrônica'],
        ['/peso|12t/', 'Peso Bruto Total Limitado'],
        // Pedestres / ciclistas
        ['/compartilhad|dividido/', 'Trânsito Compartilhado (Ciclistas e Pedestres)'],
        ['/ciclorrota|bicicleta|ciclista|ciclovi/', 'Ciclorrota / Ponto de Bicicleta'],
        ['/pedestre|travessia|faixa/', 'Passagem Sinalizada de Pedestres'],
        // Regulamentação geral
        ['/\bpare\b|parada obrigat|parado obrigat|passagem obrigat|placa de parar/', 'Parada Obrigatória (Pare)'],
        ['/preferencia/', 'Dê a Preferência'],
        ['/velocidade|\bkm\b|km\/h|\d+\s*km/', 'Velocidade Máxima Permitida'],
        ['/duplo sentido/', 'Duplo Sentido de Circulação'],
        ['/sentido|setas indicativas/', 'Sentido de Circulação da Via'],
        ['/siga|em frente|fica em frente/', 'Siga em Frente'],
        ['/estacionamento regulamenta|permitindo estacionar|placa (de )?estacion|embarque/', 'Estacionamento Regulamentado'],
        ['/indica/', 'Placa de Indicação'],
    ];

    /** De/para dos horizontais (nomes limpos do levantamento). */
    private const REGRAS_HORIZONTAL = [
        ['/faixa.*pedestre/', 'Faixa para Pedestres'],
        ['/travessia elevada/', 'Travessia Elevada'],
        ['/lombada|quebra.?mola/', 'Lombada / Quebra-molas'],
        ['/ciclovi/', 'Ciclovia (pintura)'],
    ];

    /** Correções pontuais de valores dos trechos (typos do ditado por voz). */
    private const VALORES_TRECHO = [
        'Arbustro' => 'Arbusto',
        'Piso drenante (INTERTRAVADO)' => 'Piso drenante (intertravado)',
        'Vaga rápido (embarque e desembarque)' => 'Vaga rápida (embarque e desembarque)',
        'Vaga de idoso e PCD (pessoa com deficiência)' => 'Vaga de idoso, Vaga PCD (pessoa com deficiência)',
    ];

    private array $relatorio = [];

    public function handle(): int
    {
        $pasta = rtrim($this->argument('pasta'), '/\\');
        $saida = rtrim($this->option('saida') ?: $pasta.DIRECTORY_SEPARATOR.'normalizado', '/\\');

        if (! is_dir($pasta)) {
            $this->error("Pasta não encontrada: {$pasta}");

            return self::FAILURE;
        }
        @mkdir($saida, 0777, true);

        // bbox do município (trechos + bairros) p/ recorte das rodovias
        $bbox = $this->bboxDe([$this->ler($pasta, 'trechos.json'), $this->ler($pasta, 'bairros.json')], margem: 0.02);

        $this->normalizarTrechos($pasta, $saida);
        $this->normalizarSinalizacao($pasta, $saida);
        $this->normalizarPois($pasta, $saida);
        $this->normalizarEixos($pasta, $saida, $bbox);
        $this->normalizarZonas($pasta, $saida);
        $this->normalizarFluxos($pasta, $saida);

        // bairros passam direto (camada Bairro já existente no sistema)
        if ($b = $this->ler($pasta, 'bairros.json')) {
            $this->gravar($saida, 'bairros.json', $b['features']);
            $this->relatorio[] = 'bairros.json: '.count($b['features']).' bairros (cópia — importar como camada Bairro)';
        }

        $this->newLine();
        foreach ($this->relatorio as $linha) {
            $this->line('  • '.$linha);
        }
        file_put_contents($saida.DIRECTORY_SEPARATOR.'relatorio.txt', implode("\n", $this->relatorio)."\n");
        $this->newLine();
        $this->info("Saída: {$saida}");

        return self::SUCCESS;
    }

    // ── Camadas ─────────────────────────────────────────────────────────────

    private function normalizarTrechos(string $pasta, string $saida): void
    {
        $json = $this->ler($pasta, 'trechos.json');
        if (! $json) {
            return;
        }

        // opções do kit por slug (p/ casar valores com case/typos divergentes)
        $opcoesKit = [];
        foreach (MobilidadeSeeder::KIT as [$entidade, $slug, $label, $tipo, $opcoes]) {
            if ($entidade === 'mob_trecho' && $opcoes) {
                $opcoesKit[$slug] = $opcoes;
            }
        }

        $naoCasados = [];
        $out = [];
        foreach ($json['features'] as $f) {
            $p = (array) $f['properties'];

            // typo do levantamento: "esuqerdo"
            if (array_key_exists('estado_conservacao_calcada_esuqerdo', $p)) {
                $p['estado_conservacao_calcada_esquerdo'] = $p['estado_conservacao_calcada_esuqerdo'];
                unset($p['estado_conservacao_calcada_esuqerdo']);
            }
            unset($p['name']); // é só um número; a referência do trecho é o sequential_id

            foreach ($p as $chave => $valor) {
                if (! is_string($valor) || $valor === '') {
                    continue;
                }
                $valor = self::VALORES_TRECHO[$valor] ?? $valor;

                // casa com a opção do kit ignorando caixa; "não possui ..." vira a opção "Não possui"
                if (isset($opcoesKit[$chave])) {
                    $p[$chave] = $this->casarComOpcoes($valor, $opcoesKit[$chave], $chave, $naoCasados);
                } else {
                    $p[$chave] = $valor;
                }
            }

            $out[] = ['type' => 'Feature', 'properties' => $p, 'geometry' => $f['geometry']];
        }

        $this->gravar($saida, 'mob_trechos.json', $out);
        $this->relatorio[] = 'mob_trechos.json: '.count($out).' trechos (26 atributos; sentido = null p/ classificar na Onda 4)';
        foreach (array_slice(array_keys($naoCasados), 0, 15) as $nc) {
            $this->relatorio[] = "    ⚠ valor fora das opções do kit: {$nc}";
        }
    }

    private function normalizarSinalizacao(string $pasta, string $saida): void
    {
        $porTipo = [];
        $semClassificacao = [];
        $out = [];
        $id = 0;

        $fontes = [['sinalizacao.json', null], ['sinalizacao_estacionamento.json', 'vertical']];
        foreach ($fontes as [$arquivo, $tipoFixo]) {
            $json = $this->ler($pasta, $arquivo);
            if (! $json) {
                continue;
            }
            foreach ($json['features'] as $f) {
                $props = (array) $f['properties'];
                $nomeCru = trim((string) ($props['name'] ?? $props['Name'] ?? ''));
                $tipo = $tipoFixo ?? (strtolower((string) ($props['tipo'] ?? 'Vertical')) === 'horizontal' ? 'horizontal' : 'vertical');

                $nomeCatalogo = $this->classificarSinalizacao($nomeCru, $tipo);
                if ($nomeCatalogo === null) {
                    $semClassificacao[$this->limparNome($nomeCru)] = true;
                }
                $rotulo = $nomeCatalogo ?? 'A Classificar';
                $porTipo[$rotulo] = ($porTipo[$rotulo] ?? 0) + 1;

                $out[] = ['type' => 'Feature', 'properties' => [
                    'id' => ++$id,
                    'tipo_sinalizacao' => $rotulo,
                    'tipo_sinalizacao_tipo' => $tipo,
                    'descricao_original' => $nomeCru,
                    'origem' => $arquivo.'#'.($props['id'] ?? '?'),
                ], 'geometry' => $f['geometry']];
            }
        }

        $this->gravar($saida, 'mob_sinalizacoes.json', $out);
        arsort($porTipo);
        $aClassificar = $porTipo['A Classificar'] ?? 0;
        $this->relatorio[] = 'mob_sinalizacoes.json: '.count($out)." placas/marcações — {$aClassificar} sem classificação (caem no tipo \"A Classificar\")";
        foreach ($porTipo as $nome => $qtd) {
            $this->relatorio[] = sprintf('    %4d × %s', $qtd, $nome);
        }
        foreach (array_slice(array_keys($semClassificacao), 0, 30) as $nome) {
            $this->relatorio[] = "    ⚠ não classificado: {$nome}";
        }
    }

    private function normalizarPois(string $pasta, string $saida): void
    {
        $out = [];
        $id = 0;
        foreach (self::POIS as [$arquivo, $categoria]) {
            $json = $this->ler($pasta, $arquivo);
            if (! $json) {
                continue;
            }
            foreach ($json['features'] as $f) {
                $props = (array) $f['properties'];
                $out[] = ['type' => 'Feature', 'properties' => [
                    'id' => ++$id,
                    'categoria' => $categoria,
                    'name' => trim((string) ($props['name'] ?? $props['Name'] ?? '')),
                    'numero' => isset($props['numero']) ? (string) $props['numero'] : null,
                    'origem' => $arquivo.'#'.($props['id'] ?? '?'),
                ], 'geometry' => $f['geometry']];
            }
        }
        $this->gravar($saida, 'mob_pontos_interesse.json', $out);
        $this->relatorio[] = 'mob_pontos_interesse.json: '.count($out).' pontos em 7 categorias';
    }

    private function normalizarEixos(string $pasta, string $saida, array $bbox): void
    {
        $out = [];
        $id = 0;

        $simples = [
            ['rede_cicloviaria.json', 'ciclovia'],
            ['eixos_comerciais.json', 'eixo_comercial'],
            ['rotas_transporte_cargas.json', 'rota_carga'],
        ];
        foreach ($simples as [$arquivo, $tipo]) {
            $json = $this->ler($pasta, $arquivo);
            if (! $json) {
                continue;
            }
            foreach ($json['features'] as $f) {
                $props = (array) $f['properties'];
                $out[] = ['type' => 'Feature', 'properties' => [
                    'id' => ++$id,
                    'tipo' => $tipo,
                    'name' => trim((string) ($props['name'] ?? '')), // decisão 6.7: como veio
                    'origem' => $arquivo.'#'.($props['id'] ?? '?'),
                ], 'geometry' => $f['geometry']];
            }
        }

        // Rodovias DER-ES: só o que intersecta o bbox do município (decisão P3)
        $json = $this->ler($pasta, 'rodovias.json');
        $mantidas = 0;
        $descartadas = 0;
        if ($json) {
            foreach ($json['features'] as $f) {
                if (! $this->intersectaBbox($f['geometry'] ?? null, $bbox)) {
                    $descartadas++;

                    continue;
                }
                $props = (array) $f['properties'];
                $nome = trim(implode(' — ', array_filter([
                    trim((string) ($props['sigla'] ?? '')),
                    trim((string) ($props['descricao'] ?? '')),
                ]))) ?: 'Rodovia';

                $out[] = ['type' => 'Feature', 'properties' => [
                    'id' => ++$id,
                    'tipo' => 'rodovia',
                    'name' => $nome,
                    // extras DER-ES → campos customizados do kit mob_eixo
                    'sigla_rodovia' => $props['sigla'] ?? null,
                    'sre' => $props['sre'] ?? null,
                    'situacao_der' => $props['sit'] ?? null,
                    'km_inicial' => $props['km_inicial'] ?? null,
                    'km_final' => $props['km_final'] ?? null,
                    'origem' => 'rodovias.json#'.($props['id'] ?? '?'),
                ], 'geometry' => $f['geometry']];
                $mantidas++;
            }
        }

        $this->gravar($saida, 'mob_eixos.json', $out);
        $this->relatorio[] = 'mob_eixos.json: '.count($out)." eixos (rodovias: {$mantidas} dentro do município, {$descartadas} descartadas do resto do ES)";
    }

    private function normalizarZonas(string $pasta, string $saida): void
    {
        $out = [];
        $id = 0;

        $fontes = [
            ['zonas.json', 'zona_od'],
            ['poligonal.json', 'quadrante'],
            ['polo_industrial.json', 'polo_industrial'],
            ['setores.json', 'setor_censitario'],
        ];
        foreach ($fontes as [$arquivo, $tipo]) {
            $json = $this->ler($pasta, $arquivo);
            if (! $json) {
                continue;
            }
            foreach ($json['features'] as $f) {
                $props = (array) $f['properties'];
                $out[] = ['type' => 'Feature', 'properties' => array_filter([
                    'id' => ++$id,
                    'tipo' => $tipo,
                    'name' => trim((string) ($props['name'] ?? ($tipo === 'setor_censitario' ? 'Setor '.($props['codigo'] ?? $id) : ''))),
                    'codigo' => isset($props['codigo']) ? (string) $props['codigo'] : null,
                    'situacao' => $props['situacao'] ?? null,
                    'origens' => $props['origens'] ?? null,
                    'destinos' => $props['destinos'] ?? null,
                    'origem' => $arquivo.'#'.($props['id'] ?? '?'),
                    // area_geo do arquivo é descartada (unidade suspeita) — decisão 6.6
                ], fn ($v) => $v !== null && $v !== ''), 'geometry' => $f['geometry']];
            }
        }

        $this->gravar($saida, 'mob_zonas.json', $out);
        $this->relatorio[] = 'mob_zonas.json: '.count($out).' zonas (O/D + quadrantes + polo + setores IBGE; áreas recalculadas via PostGIS)';
    }

    private function normalizarFluxos(string $pasta, string $saida): void
    {
        $json = $this->ler($pasta, 'fluxos.json');
        if (! $json) {
            return;
        }

        $porChave = [];
        foreach ($json['features'] as $f) {
            $props = (array) $f['properties'];
            $destino = $this->semAcentos(mb_strtolower(trim((string) ($props['fluxo'] ?? ''))));
            $chave = $destino.'|'.md5(json_encode($f['geometry']['coordinates'] ?? []));

            $valores = (int) ($props['valores'] ?? 0);
            // dedup por geometria idêntica (decisão P4): fica a de maior volume
            if (! isset($porChave[$chave]) || $valores > $porChave[$chave]['valores']) {
                $porChave[$chave] = ['destino' => $destino, 'valores' => $valores, 'geometry' => $f['geometry'], 'origem' => $props['id'] ?? '?'];
            }
        }

        $out = [];
        $id = 0;
        foreach ($porChave as $item) {
            $out[] = ['type' => 'Feature', 'properties' => [
                'id' => ++$id,
                'destino' => $item['destino'],
                'valores' => $item['valores'],
                'origem' => 'fluxos.json#'.$item['origem'],
            ], 'geometry' => $item['geometry']];
        }

        $duplicados = count($json['features']) - count($out);
        $this->gravar($saida, 'mob_fluxos.json', $out);
        $this->relatorio[] = 'mob_fluxos.json: '.count($out)." linhas de desejo ({$duplicados} duplicata(s) por geometria idêntica removida(s))";
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function classificarSinalizacao(string $nomeCru, string $tipo): ?string
    {
        $texto = $this->semAcentos(mb_strtolower($this->limparNome($nomeCru)));
        if ($texto === '') {
            return null;
        }

        $regras = $tipo === 'horizontal' ? self::REGRAS_HORIZONTAL : self::REGRAS_SINALIZACAO;
        foreach ($regras as [$regex, $nomeCatalogo]) {
            if (preg_match($regex, $texto)) {
                return $nomeCatalogo;
            }
        }

        return null;
    }

    /** Remove o timestamp que a coleta por voz colou no nome ("... 14/01/2026, 10:09:56"). */
    private function limparNome(string $nome): string
    {
        return trim(preg_replace('/\s*\d{1,2}\/\d{1,2}\/\d{4}.*$/u', '', $nome));
    }

    private function semAcentos(string $s): string
    {
        return strtr($s, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
            'Á' => 'a', 'À' => 'a', 'Ã' => 'a', 'Â' => 'a', 'É' => 'e', 'Ê' => 'e', 'Í' => 'i',
            'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ç' => 'c',
        ]);
    }

    /** Casa um valor com as opções do kit (case-insensitive; "não possui ..." → opção "Não possui"). */
    private function casarComOpcoes(string $valor, array $opcoes, string $slug, array &$naoCasados): string
    {
        foreach ($opcoes as $opcao) {
            if (mb_strtolower($opcao) === mb_strtolower($valor)) {
                return $opcao;
            }
        }
        if (str_contains($this->semAcentos(mb_strtolower($valor)), 'nao possui')) {
            foreach ($opcoes as $opcao) {
                if (str_contains($this->semAcentos(mb_strtolower($opcao)), 'nao possui')) {
                    return $opcao;
                }
            }
        }
        // multipla: casa item a item
        if (str_contains($valor, ',')) {
            $itens = array_map('trim', explode(',', $valor));
            $casados = array_map(fn ($i) => $this->casarComOpcoes($i, $opcoes, $slug, $naoCasados), $itens);

            return implode(', ', $casados);
        }

        $naoCasados["{$slug}: \"{$valor}\""] = true;
        $naoCasados = array_slice($naoCasados, 0, 50, true);

        return $valor;
    }

    private function ler(string $pasta, string $arquivo): ?array
    {
        $caminho = $pasta.DIRECTORY_SEPARATOR.$arquivo;
        if (! is_file($caminho)) {
            $this->warn("  arquivo ausente: {$arquivo}");

            return null;
        }
        $json = json_decode(file_get_contents($caminho), true);
        if (! is_array($json) || ! isset($json['features'])) {
            $this->warn("  JSON inválido: {$arquivo}");

            return null;
        }

        return $json;
    }

    private function gravar(string $saida, string $arquivo, array $features): void
    {
        file_put_contents(
            $saida.DIRECTORY_SEPARATOR.$arquivo,
            json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /** bbox [minLon, minLat, maxLon, maxLat] das coleções dadas, com margem em graus. */
    private function bboxDe(array $colecoes, float $margem): array
    {
        $bbox = [INF, INF, -INF, -INF];
        $anda = function ($c) use (&$anda, &$bbox) {
            if (is_array($c) && isset($c[0]) && is_numeric($c[0]) && ! is_array($c[0])) {
                $bbox[0] = min($bbox[0], $c[0]);
                $bbox[1] = min($bbox[1], $c[1]);
                $bbox[2] = max($bbox[2], $c[0]);
                $bbox[3] = max($bbox[3], $c[1]);
            } elseif (is_array($c)) {
                foreach ($c as $s) {
                    $anda($s);
                }
            }
        };
        foreach ($colecoes as $json) {
            foreach (($json['features'] ?? []) as $f) {
                $anda($f['geometry']['coordinates'] ?? []);
            }
        }

        return [$bbox[0] - $margem, $bbox[1] - $margem, $bbox[2] + $margem, $bbox[3] + $margem];
    }

    /** true se o bbox da geometria cruza o bbox dado (suficiente p/ recorte municipal). */
    private function intersectaBbox(?array $geometry, array $bbox): bool
    {
        if (! $geometry) {
            return false;
        }
        $g = [INF, INF, -INF, -INF];
        $anda = function ($c) use (&$anda, &$g) {
            if (is_array($c) && isset($c[0]) && is_numeric($c[0]) && ! is_array($c[0])) {
                $g[0] = min($g[0], $c[0]);
                $g[1] = min($g[1], $c[1]);
                $g[2] = max($g[2], $c[0]);
                $g[3] = max($g[3], $c[1]);
            } elseif (is_array($c)) {
                foreach ($c as $s) {
                    $anda($s);
                }
            }
        };
        $anda($geometry['coordinates'] ?? []);

        return $g[0] <= $bbox[2] && $g[2] >= $bbox[0] && $g[1] <= $bbox[3] && $g[3] >= $bbox[1];
    }
}
