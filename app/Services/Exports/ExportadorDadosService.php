<?php

namespace App\Services\Exports;

use App\Models\Tenant;
use App\Services\Coleta\CampoCustomizadoService;
use App\Support\FotosLote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * R84-7 — "Exportar dados": motor ÚNICO do /admin (Prefeitura → Exportar dados) e do
 * mapa da prefeitura (Exportar resultado do filtro). Roda dentro da fila.
 *
 * Formatos: GeoJSON (um arquivo por camada, num ZIP) · Excel (uma aba por camada) ·
 * GeoPackage (todas as camadas num .gpkg, via ogr2ogr do GDAL).
 *
 * O arquivo é REIMPORTÁVEL pela Importação GIS do /admin (modo Atualizar):
 *  - `id` = número da prefeitura (sequential_id), nunca a PK global;
 *  - vínculos (quadra_id, lote_id, logradouro_id…) = número da prefeitura do pai;
 *  - campos customizados = uma coluna por slug, com o valor gravado;
 *  - colunas só de leitura (coleta, fotos, proprietário, nomes) têm nomes que a
 *    importação ignora (não existem na tabela).
 *
 * Recorte: por bairro(s) (polígonos pelo ponto interno — um lote nunca sai em dois
 * bairros; linhas e camadas grandes por interseção) ou por ids (resultado do filtro
 * do mapa; lotes levam junto edificações, unidades e testadas).
 */
class ExportadorDadosService
{
    /**
     * Camadas exportáveis, na ordem de importação. `entidade` = campos customizados;
     * `recorte` = como entra no recorte por bairro (ponto|intersecta|bairro|lote).
     */
    public const CAMADAS = [
        'perimetros_urbanos' => ['label' => 'Distritos / Limites', 'importar' => 'PerimetroUrbano', 'entidade' => 'perimetro', 'recorte' => 'intersecta'],
        'zonas' => ['label' => 'Zoneamento', 'importar' => 'Zona', 'entidade' => 'zona', 'recorte' => 'intersecta'],
        'setores_fiscais' => ['label' => 'Setores Fiscais', 'importar' => 'SetorFiscal', 'entidade' => 'setor_fiscal', 'recorte' => 'intersecta'], // B9 — antes dos bairros
        'bairros' => ['label' => 'Bairros', 'importar' => 'Bairro', 'entidade' => 'bairro', 'recorte' => 'bairro'],
        'loteamentos' => ['label' => 'Loteamentos', 'importar' => 'Loteamento', 'entidade' => 'loteamento', 'recorte' => 'intersecta'],
        'quadras' => ['label' => 'Quadras', 'importar' => 'Quadra', 'entidade' => 'quadra', 'recorte' => 'ponto'],
        'logradouros' => ['label' => 'Logradouros', 'importar' => 'Logradouro', 'entidade' => 'logradouro', 'recorte' => 'intersecta'],
        'secoes_logradouro' => ['label' => 'Seções de Logradouro', 'importar' => 'SecaoLogradouro', 'entidade' => 'secao_logradouro', 'recorte' => 'intersecta'],
        'lotes' => ['label' => 'Lotes (com dados da coleta)', 'importar' => 'Lote', 'entidade' => 'lote', 'recorte' => 'ponto'],
        'lote_testadas' => ['label' => 'Testadas de Lote', 'importar' => 'LoteTestada', 'entidade' => 'lote_testada', 'recorte' => 'lote'],
        'edificacoes' => ['label' => 'Edificações', 'importar' => 'Edificacao', 'entidade' => 'edificacao', 'recorte' => 'ponto'],
        'unidade_imobiliarias' => ['label' => 'Unidades Imobiliárias (com proprietário)', 'importar' => 'UnidadeImobiliaria', 'entidade' => 'unidade', 'recorte' => 'lote'],
        'face_quadras' => ['label' => 'Faces de Quadra (PGV)', 'importar' => 'FaceQuadra', 'entidade' => null, 'recorte' => 'intersecta'],
    ];

    /** Camadas do mapa (data-layer) → camada exportável. */
    public const CAMADAS_DO_MAPA = [
        'perimetros' => 'perimetros_urbanos', 'perimetros_urbanos' => 'perimetros_urbanos',
        'zonas' => 'zonas', 'setores_fiscais' => 'setores_fiscais', 'bairros' => 'bairros', 'loteamentos' => 'loteamentos', 'quadras' => 'quadras',
        'logradouros' => 'logradouros', 'secoes_logradouro' => 'secoes_logradouro', 'lotes' => 'lotes',
        'edificacoes' => 'edificacoes', 'face_quadras' => 'face_quadras',
    ];

    public const FORMATOS = [
        'gpkg' => 'GeoPackage (.gpkg) — abre direto no QGIS',
        'json' => 'JSON (GeoJSON por camada, em .zip) — o formato da Importação GIS',
        'xlsx' => 'Excel (.xlsx) — uma aba por camada',
    ];

    /** FK → tabela do pai (exportada como o NÚMERO da prefeitura do pai). */
    private const FKS = [
        'perimetro_id' => 'perimetros_urbanos', 'bairro_id' => 'bairros', 'loteamento_id' => 'loteamentos',
        'quadra_id' => 'quadras', 'zona_id' => 'zonas', 'lote_id' => 'lotes', 'logradouro_id' => 'logradouros',
        'secao_logradouro_id' => 'secoes_logradouro', 'setor_pgv_id' => 'setores_pgv', 'pgv_polo_id' => 'pgv_polos',
        'setor_fiscal_id' => 'setores_fiscais',
    ];

    /** Colunas internas que não saem (ou saem transformadas). */
    private const OCULTAS = [
        'id', 'tenant_id', 'sequential_id', 'geo', 'created_at', 'updated_at', 'deleted_at',
        'dados_customizados', 'dados_vistoria', 'dados_tributarios', 'proprietario_id',
        'foto_frontal', 'foto_lateral_esq', 'foto_lateral_dir',
    ];

    private const POR_PAGINA = 2000;

    private Tenant $tenant;

    /** @var array{tipo:string, bairro_ids?:array<int>, camada?:string, ids?:array<int>} */
    private array $recorte = ['tipo' => 'nenhum'];

    public function __construct(Tenant $tenant, array $recorte = [])
    {
        $this->tenant = $tenant;
        $this->recorte = $recorte ?: ['tipo' => 'nenhum'];
    }

    /**
     * Gera o arquivo final em $dir. @return array{0:string,1:string,2:string} [caminho, nome, resumo]
     *
     * @param  callable(int):void|null  $progresso  0..100
     */
    public function gerar(array $camadas, string $formato, string $dir, ?callable $progresso = null): array
    {
        $camadas = array_values(array_filter(array_keys(self::CAMADAS), fn ($c) => in_array($c, $camadas, true)));
        if (! $camadas) {
            throw new \RuntimeException('Nenhuma camada selecionada.');
        }

        $base = 'dados-'.$this->tenant->slug.'-'.now()->format('Ymd-His');
        $contagem = [];
        $geojsons = [];

        if ($formato === 'xlsx') {
            $caminho = $dir.'/'.$base.'.xlsx';
            $writer = SimpleExcelWriter::create($caminho);
            $primeira = true;
            foreach ($camadas as $i => $camada) {
                // Excel recusa \ / ? * : [ ] no nome da aba (e corta em 31)
                $aba = mb_substr(trim(preg_replace('#[\\\\/?*:\[\]]+#', '-', self::CAMADAS[$camada]['label'])), 0, 31);
                $primeira ? $writer->nameCurrentSheet($aba) : $writer->addNewSheetAndMakeItCurrent($aba);
                $primeira = false;
                $rotulos = $this->rotulosCustom($camada);
                $n = 0;
                foreach ($this->linhas($camada) as $linha) {
                    $writer->addRow($this->linhaExcel($linha, $rotulos));
                    $n++;
                }
                if ($n === 0) {
                    $writer->addRow(['Situação' => 'nenhum registro nesta camada'.($this->recorte['tipo'] !== 'nenhum' ? ' dentro do recorte' : '')]);
                }
                $contagem[$camada] = $n;
                $progresso && $progresso((int) (95 * ($i + 1) / count($camadas)));
            }
            $writer->close();

            return [$caminho, basename($caminho), $this->resumo($contagem)];
        }

        // GeoJSON (base também do GeoPackage)
        foreach ($camadas as $i => $camada) {
            $arquivo = $dir.'/'.$camada.'.geojson';
            $contagem[$camada] = $this->escreverGeoJson($camada, $arquivo, planificar: $formato === 'gpkg');
            if ($contagem[$camada] > 0) {
                $geojsons[$camada] = $arquivo;
            }
            $progresso && $progresso((int) (($formato === 'gpkg' ? 60 : 90) * ($i + 1) / count($camadas)));
        }

        if (! $geojsons) {
            throw new \RuntimeException('Nenhum registro encontrado nas camadas escolhidas'.($this->recorte['tipo'] !== 'nenhum' ? ' dentro do recorte.' : '.'));
        }

        if ($formato === 'gpkg') {
            $caminho = $dir.'/'.$base.'.gpkg';
            $this->montarGeoPackage($geojsons, $caminho);

            return [$caminho, basename($caminho), $this->resumo($contagem)];
        }

        $caminho = $dir.'/'.$base.'.zip';
        $zip = new ZipArchive;
        $zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($geojsons as $camada => $arquivo) {
            $zip->addFile($arquivo, sprintf('%02d-%s.geojson', array_search($camada, array_keys(self::CAMADAS), true) + 1, $camada));
        }
        $zip->addFromString('LEIAME.txt', $this->leiame($contagem));
        $zip->close();

        return [$caminho, basename($caminho), $this->resumo($contagem)];
    }

    // ── Leitura ─────────────────────────────────────────────────────────

    /**
     * Linhas da camada (paginadas por id — sem carregar a camada inteira na memória).
     *
     * @return \Generator<array{props:array, gj:?string, lat:?float, lon:?float}>
     */
    public function linhas(string $camada): \Generator
    {
        [$sql, $bindings] = $this->consulta($camada);
        $ultimo = 0;
        $customs = $this->camposCustom($camada);

        while (true) {
            $pagina = DB::select($sql, array_merge($bindings, [$ultimo]));
            if (! $pagina) {
                return;
            }

            foreach ($pagina as $r) {
                $ultimo = $r->_pk;
                yield $this->montarLinha($camada, $r, $customs);
            }

            if (count($pagina) < self::POR_PAGINA) {
                return;
            }
        }
    }

    /** SQL da camada com o recorte; o último binding é o cursor (id > ?). */
    private function consulta(string $camada): array
    {
        $colunas = Schema::getColumnListing($camada);
        $sel = ['t.id AS _pk', 't.sequential_id AS id'];

        foreach ($colunas as $c) {
            if (in_array($c, self::OCULTAS, true)) {
                continue;
            }
            if (isset(self::FKS[$c])) {
                $sel[] = "(SELECT p.sequential_id FROM ".self::FKS[$c]." p WHERE p.id = t.{$c}) AS {$c}";

                continue;
            }
            $sel[] = "t.{$c}";
        }

        $sel[] = 't.updated_at AS atualizado_em';
        if (in_array('dados_customizados', $colunas, true)) {
            $sel[] = 't.dados_customizados AS _custom';
        }
        // 15 casas = sem perda: reimportar sem editar devolve a mesma geometria
        $sel[] = 'ST_AsGeoJSON(t.geo, 15) AS _gj';
        $sel[] = 'ST_Y(ST_PointOnSurface(t.geo)) AS _lat';
        $sel[] = 'ST_X(ST_PointOnSurface(t.geo)) AS _lon';

        $join = '';
        // Leitura humana: nomes dos pais mais usados (a importação ignora — não são colunas)
        if ($camada === 'lotes') {
            $join = ' LEFT JOIN quadras q ON q.id = t.quadra_id LEFT JOIN bairros b ON b.id = q.bairro_id
                LEFT JOIN coleta_imobiliaria c ON c.coletavel_type = ? AND c.coletavel_id = t.id AND c.campanha = ? AND c.deleted_at IS NULL
                LEFT JOIN users u ON u.id = c.coletado_por_id';
            array_push($sel, 'q.name AS quadra_nome', 'b.name AS bairro_nome',
                't.foto_frontal AS _foto_frontal', 't.foto_lateral_esq AS _foto_lateral_esq', 't.foto_lateral_dir AS _foto_lateral_dir',
                'c.status AS coleta_status', 'u.name AS coleta_coletor', 'c.coletado_em AS coleta_data',
                'c.observacao AS coleta_observacao', 'c.inconformidade_descricao AS coleta_inconformidade');
        } elseif ($camada === 'unidade_imobiliarias') {
            $join = ' LEFT JOIN pessoas pe ON pe.id = t.proprietario_id';
            array_push($sel, 'pe.name AS proprietario_nome', 'COALESCE(pe.cpf, pe.cnpj) AS proprietario_cpf_cnpj', 't.dados_tributarios AS _tributario');
        } elseif ($camada === 'quadras') {
            $join = ' LEFT JOIN bairros b ON b.id = t.bairro_id';
            $sel[] = 'b.name AS bairro_nome';
        } elseif (in_array($camada, ['secoes_logradouro', 'lote_testadas', 'face_quadras'], true)) {
            $join = ' LEFT JOIN logradouros lg ON lg.id = t.logradouro_id';
            $sel[] = 'lg.name AS logradouro_nome';
        }

        $bindings = $camada === 'lotes' ? ['App\\Models\\Lote', \App\Models\ColetaImobiliaria::CAMPANHA_PADRAO] : [];
        $bindings[] = $this->tenant->id;
        [$filtro, $bindFiltro] = $this->filtroRecorte($camada);
        $bindings = array_merge($bindings, $bindFiltro);

        $sql = 'SELECT '.implode(', ', $sel)." FROM {$camada} t{$join}
            WHERE t.tenant_id = ? AND t.deleted_at IS NULL {$filtro} AND t.id > ?
            ORDER BY t.id LIMIT ".self::POR_PAGINA;

        return [$sql, $bindings];
    }

    private function filtroRecorte(string $camada): array
    {
        $r = $this->recorte;

        if ($r['tipo'] === 'ids') {
            $ids = array_values(array_map('intval', $r['ids'] ?? [])) ?: [0];
            $ph = implode(',', array_fill(0, count($ids), '?'));
            if ($camada === $r['camada']) {
                return [" AND t.id IN ({$ph})", $ids];
            }
            // Lotes do filtro levam junto as camadas filhas
            if ($r['camada'] === 'lotes' && in_array($camada, ['edificacoes', 'unidade_imobiliarias', 'lote_testadas'], true)) {
                return [" AND t.lote_id IN ({$ph})", $ids];
            }

            return [' AND FALSE', []];
        }

        if ($r['tipo'] !== 'bairros' || empty($r['bairro_ids'])) {
            return ['', []];
        }

        $ids = array_values(array_map('intval', $r['bairro_ids']));
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rec = "(SELECT ST_Union(ST_MakeValid(geo::geometry)) FROM bairros WHERE tenant_id = ? AND id IN ({$ph}))";
        $bind = array_merge([$this->tenant->id], $ids);

        return match (self::CAMADAS[$camada]['recorte']) {
            'bairro' => [" AND t.id IN ({$ph})", $ids],
            'ponto' => [" AND t.geo IS NOT NULL AND ST_Intersects({$rec}, ST_PointOnSurface(t.geo::geometry))", $bind],
            'lote' => [" AND EXISTS (SELECT 1 FROM lotes l WHERE l.id = t.lote_id AND l.geo IS NOT NULL AND ST_Intersects({$rec}, ST_PointOnSurface(l.geo::geometry)))", $bind],
            default => [" AND t.geo IS NOT NULL AND ST_Intersects(t.geo::geometry, {$rec})", $bind],
        };
    }

    private function camposCustom(string $camada): array
    {
        $entidade = self::CAMADAS[$camada]['entidade'];

        return $entidade
            ? CampoCustomizadoService::definicoes($entidade, $this->tenant->id)->pluck('label', 'slug')->all()
            : [];
    }

    private function rotulosCustom(string $camada): array
    {
        return $this->camposCustom($camada);
    }

    private function montarLinha(string $camada, object $r, array $customs): array
    {
        $props = [];
        foreach ((array) $r as $k => $v) {
            if ($k[0] !== '_') {
                $props[$k] = $v;
            }
        }

        // Campos customizados do município: uma coluna por slug (todas as definidas, mesmo vazias)
        $dados = isset($r->_custom) && $r->_custom ? (json_decode($r->_custom, true) ?: []) : [];
        foreach ($customs as $slug => $label) {
            $props[$slug] = $dados[$slug] ?? null;
        }

        if ($camada === 'lotes') {
            foreach (['frontal', 'lateral_esq', 'lateral_dir'] as $f) {
                $props['foto_'.$f.'_url'] = FotosLote::url($r->{'_foto_'.$f} ?? null);
            }
        }
        if ($camada === 'unidade_imobiliarias') {
            $props['dados_tributarios'] = $r->_tributario ?? null;
        }

        return [
            'props' => $props,
            'gj' => $r->_gj ?? null,
            'lat' => isset($r->_lat) ? round((float) $r->_lat, 7) : null,
            'lon' => isset($r->_lon) ? round((float) $r->_lon, 7) : null,
            'customs' => array_keys($customs),
        ];
    }

    // ── Escrita ─────────────────────────────────────────────────────────

    /** GeoJSON em streaming. planificar = listas viram "a, b" (o GeoPackage não tem lista). */
    private function escreverGeoJson(string $camada, string $arquivo, bool $planificar): int
    {
        $fh = fopen($arquivo, 'wb');
        fwrite($fh, '{"type":"FeatureCollection","name":'.json_encode($camada).',"crs":{"type":"name","properties":{"name":"urn:ogc:def:crs:OGC:1.3:CRS84"}},"features":[');
        $n = 0;

        foreach ($this->linhas($camada) as $linha) {
            $props = $linha['props'];
            if ($camada === 'unidade_imobiliarias' && $planificar === false && is_string($props['dados_tributarios'] ?? null)) {
                $props['dados_tributarios'] = json_decode($props['dados_tributarios'], true);
            }
            if ($planificar) {
                foreach ($props as $k => $v) {
                    if (is_array($v)) {
                        $props[$k] = implode(', ', array_map(fn ($x) => is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE), $v));
                    }
                }
            }

            fwrite($fh, ($n ? ',' : '').'{"type":"Feature","properties":'.json_encode($props, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
                .',"geometry":'.($linha['gj'] ?: 'null').'}');
            $n++;
        }

        fwrite($fh, ']}');
        fclose($fh);

        return $n;
    }

    private function linhaExcel(array $linha, array $rotulos): array
    {
        $saida = [];
        foreach ($linha['props'] as $k => $v) {
            if ($k === 'dados_tributarios') {
                continue; // JSON bruto não cabe na célula (32 mil caracteres) — vai no JSON/GeoPackage
            }
            $cab = isset($rotulos[$k]) ? $rotulos[$k].' ('.$k.')' : $k;
            $saida[$cab] = match (true) {
                is_array($v) => implode(', ', array_map(fn ($x) => is_scalar($x) ? (string) $x : json_encode($x), $v)),
                is_bool($v) => $v ? 'Sim' : 'Não',
                default => $v,
            };
        }
        $saida['latitude_centro'] = $linha['lat'];
        $saida['longitude_centro'] = $linha['lon'];

        return $saida;
    }

    /** Junta os GeoJSONs num .gpkg (uma camada por arquivo) com o ogr2ogr do GDAL. */
    private function montarGeoPackage(array $geojsons, string $destino): void
    {
        $env = [];
        // OSGeo4W no Windows (ambiente local) não acha o proj.db sem isto
        foreach (['C:\\OSGeo4W\\share\\proj', 'C:\\OSGeo4W64\\share\\proj'] as $dirProj) {
            if (PHP_OS_FAMILY === 'Windows' && is_dir($dirProj)) {
                $env = ['PROJ_DATA' => $dirProj, 'PROJ_LIB' => $dirProj];
                break;
            }
        }

        $primeira = true;
        foreach ($geojsons as $camada => $arquivo) {
            $args = ['ogr2ogr', '-f', 'GPKG'];
            if (! $primeira) {
                $args[] = '-update';
            }
            array_push($args, $destino, $arquivo, '-nln', $camada, '-lco', 'FID=gpkg_fid');
            if ($camada !== 'unidade_imobiliarias') {
                array_push($args, '-nlt', 'PROMOTE_TO_MULTI');
            }

            $p = new Process($args, null, $env ?: null);
            $p->setTimeout(1800);
            $p->run();
            if (! $p->isSuccessful() || ! is_file($destino)) {
                throw new \RuntimeException("GeoPackage: falha ao gravar a camada {$camada} — ".trim($p->getErrorOutput() ?: $p->getOutput()));
            }
            $primeira = false;
        }
    }

    private function resumo(array $contagem): string
    {
        $partes = [];
        foreach ($contagem as $camada => $n) {
            $partes[] = mb_strtolower(explode(' (', self::CAMADAS[$camada]['label'])[0]).': '.number_format($n, 0, ',', '.');
        }

        return implode(' · ', $partes);
    }

    private function leiame(array $contagem): string
    {
        $linhas = [
            'SIGWEB — Exportação de dados — '.$this->tenant->name,
            'Gerado em '.now()->format('d/m/Y H:i'),
            '',
            'Arquivos (na ordem de importação):',
        ];
        foreach ($contagem as $camada => $n) {
            $linhas[] = sprintf('  - %s: %s registro(s) — reimportar como "%s"', $camada, $n, self::CAMADAS[$camada]['importar']);
        }

        return implode("\r\n", array_merge($linhas, [
            '',
            'Como voltar com os dados ao SIGWEB:',
            '  /admin → Prefeituras → Importar Mapa (GIS) → camada correspondente → modo "Atualizar".',
            '  "id" é o NÚMERO do registro na prefeitura: mantenha-o. Feição nova = deixe o "id" vazio.',
            '  Os vínculos (quadra_id, lote_id, logradouro_id...) também usam o número da prefeitura do pai.',
            '',
            'Colunas só de leitura (ignoradas na importação):',
            '  nomes (quadra_nome, bairro_nome, logradouro_nome), dados da coleta (coleta_*), links das fotos (foto_*_url),',
            '  proprietário (proprietario_*), dados_tributarios, atualizado_em, área/extensão (recalculadas pela geometria).',
            '',
            'Coordenadas em WGS 84 (EPSG:4326).',
        ]));
    }
}
