<?php

namespace App\Services\Gis;

use App\Models\CampoCustomizado;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Motor da ação "Importar Mapa (GIS)" do painel /admin (TenantResource).
 *
 * Modos (T2.6):
 *  - adicionar:  todos os registros do arquivo entram como novos (comportamento original);
 *  - atualizar:  casa pelo id do arquivo (tenant_id + sequential_id) — atualiza o registro
 *                vivo quando existe e cria os demais. Preserva TODOS os vínculos (unidades,
 *                processos digitais, coletas), porque o registro é o mesmo. É o modo certo
 *                para reimportar depois de editar a cartografia no QGIS;
 *  - substituir: soft-delete de toda a camada ANTES de importar, dentro da mesma transação
 *                (se a importação falhar, nada é apagado) e, ao final, reconexão dos filhos
 *                por sequential_id — FKs descobertas via information_schema + coleta
 *                polimórfica passam a apontar para o registro novo de mesmo número.
 */
class ImportadorGisService
{
    /** Camada do seletor → entidade de campo customizado (item 75). */
    public const ENTIDADE_CUSTOM = [
        'Lote' => 'lote',
        'Edificacao' => 'edificacao',
        'UnidadeImobiliaria' => 'unidade',
        'Quadra' => 'quadra',
        'Bairro' => 'bairro',
        'Logradouro' => 'logradouro',
        'Loteamento' => 'loteamento',
        'Zona' => 'zona',
        'PerimetroUrbano' => 'perimetro',
        // Mobilidade Urbana (docs/piuma.txt, Onda 1)
        'MobTrecho' => 'mob_trecho',
        'MobEixo' => 'mob_eixo',
    ];

    /**
     * @param  array  $features  features do GeoJSON já decodificado (objetos stdClass)
     * @return array{total:int, criados:int, atualizados:int, apagados:int, reconectados:array<string,int>, nao_resolvidos:array<string,int>}
     */
    public static function importar(Tenant $tenant, string $camada, array $features, string $modo = 'adicionar'): array
    {
        $modelClass = 'App\\Models\\'.$camada;
        $agrupados = [];

        // 1. INTELIGÊNCIA GEOGRÁFICA DE AGRUPAMENTO
        foreach ($features as $feature) {
            $props = $feature->properties;
            $id = $props->id ?? $props->fid ?? uniqid();

            if (! isset($agrupados[$id])) {
                $agrupados[$id] = ['props' => $props, 'coords' => [], 'type' => null];
            }

            // Proteção real contra propriedades sem mapa (geometry = null)
            if (isset($feature->geometry) && ! empty($feature->geometry) && isset($feature->geometry->type)) {
                $geomType = $feature->geometry->type;

                if (in_array($geomType, ['Polygon', 'MultiPolygon'])) {
                    $agrupados[$id]['type'] = 'MultiPolygon';
                    if ($geomType === 'Polygon') {
                        $agrupados[$id]['coords'][] = $feature->geometry->coordinates;
                    } else {
                        foreach ($feature->geometry->coordinates as $poly) {
                            $agrupados[$id]['coords'][] = $poly;
                        }
                    }
                } elseif (in_array($geomType, ['LineString', 'MultiLineString'])) {
                    $agrupados[$id]['type'] = 'MultiLineString';
                    if ($geomType === 'LineString') {
                        $agrupados[$id]['coords'][] = $feature->geometry->coordinates;
                    } else {
                        foreach ($feature->geometry->coordinates as $line) {
                            $agrupados[$id]['coords'][] = $line;
                        }
                    }
                } elseif ($geomType === 'Point') {
                    $agrupados[$id]['type'] = 'Point';
                    $agrupados[$id]['coords'] = $feature->geometry->coordinates;
                }
            }
        }

        // Referências do JSON que não existem nesta prefeitura (relatadas ao final)
        $naoResolvidos = [];
        $criados = 0;
        $atualizados = 0;
        $apagados = 0;
        $reconectados = [];

        // Helper para buscar o ID global real no banco com base no ID do JSON (salvo como sequential_id).
        // ⚠️ Sem correspondência o vínculo fica NULO: usar o número do JSON como id global
        // amarraria o registro na entidade de OUTRA prefeitura (a PK é sequência global).
        $resolveRelacionamento = function ($modelStr, $jsonId) use ($tenant, &$naoResolvidos) {
            if (! $jsonId) {
                return null;
            }

            $realId = $modelStr::where('tenant_id', $tenant->id)->where('sequential_id', $jsonId)->value('id');

            if (! $realId) {
                $rotulo = class_basename($modelStr);
                $naoResolvidos[$rotulo] = ($naoResolvidos[$rotulo] ?? 0) + 1;
            }

            return $realId;
        };

        // R67-1 / item 75 — campos customizados do município para esta camada:
        // uma property do GeoJSON com o mesmo nome do identificador do campo
        // é importada para `dados_customizados`. Carregado UMA vez (o painel
        // admin não tem tenant Filament → filtro explícito).
        $entidadeCustom = self::ENTIDADE_CUSTOM[$camada] ?? null;

        $camposCustom = $entidadeCustom
            ? CampoCustomizado::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('entidade', $entidadeCustom)
                ->where('ativo', true)
                ->whereNull('deleted_at')
                ->get()
            : collect();

        DB::beginTransaction();
        try {
            // MODO SUBSTITUIR — soft-delete em massa ANTES de importar. O marcador
            // (timestamp único desta operação) delimita exatamente o que foi apagado
            // agora, para a reconexão não mexer em lixeira antiga.
            $marcador = null;
            if ($modo === 'substituir') {
                $marcador = now();
                $apagados = $modelClass::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => $marcador]);
            }

            foreach ($agrupados as $originalId => $item) {
                $props = $item['props'];

                // 2. PREENCHIMENTO BASE
                $fillData = [
                    'tenant_id' => $tenant->id,
                    'code' => (string) Str::uuid(),
                ];

                // TRATAMENTO DA GEOMETRIA (Preenche ou deixa Nulo para imóveis sem mapa)
                if (! empty($item['type'])) {
                    $fillData['geo'] = [
                        'type' => $item['type'],
                        'coordinates' => $item['coords'],
                    ];
                } else {
                    $fillData['geo'] = null;
                }

                // MOBILIDADE URBANA (docs/piuma.txt, Onda 1): mapeamento próprio —
                // as regras legadas abaixo assumem colunas do imobiliário (code,
                // name, numero_lote, codigo, tipo...) que as tabelas mob_* não têm.
                if (str_starts_with($camada, 'Mob')) {
                    unset($fillData['code']); // tabelas mob_* não têm coluna code
                    $fillData = array_merge($fillData, self::mapearCamposMob($camada, $props, $tenant->id));
                    // Onda 6: trecho aponta p/ a Via Urbana pelo NÚMERO da via no JSON
                    // (importar as vias ANTES dos trechos; ausente = vínculo nulo, contado)
                    if ($camada === 'MobTrecho' && isset($props->via_id) && $props->via_id !== '') {
                        $fillData['via_id'] = $resolveRelacionamento(\App\Models\MobVia::class, $props->via_id);
                    }
                } else {

                // A REGRA DO NOME (Necessária para Perímetros, Zonas, Logradouros, etc)
                $camadasComNome = ['PerimetroUrbano', 'Zona', 'Bairro', 'Loteamento', 'Quadra', 'Logradouro'];
                if (in_array($camada, $camadasComNome)) {
                    $fillData['name'] = $props->name ?? $props->numero_lote ?? 'Sem Nome';
                }

                // 3. MAPEAMENTO DINÂMICO
                if (isset($props->distrito)) {
                    $fillData['distrito'] = $props->distrito;
                }
                if (isset($props->sigla)) {
                    $fillData['sigla'] = $props->sigla;
                }
                if (isset($props->rgb)) {
                    $fillData['rgb'] = $props->rgb;
                }
                // Refatoração PoC Tangará: 'setor' saiu; o código
                // municipal (itens 44-49) entra pela property 'codigo'.
                if (isset($props->codigo)) {
                    $fillData['codigo'] = $props->codigo;
                }

                // 🛑 A MÁGICA DOS RELACIONAMENTOS: Traduz o ID do JSON para o ID Real do Banco
                if (isset($props->perimetro_id)) {
                    $fillData['perimetro_id'] = $resolveRelacionamento(\App\Models\PerimetroUrbano::class, $props->perimetro_id);
                }
                if (isset($props->bairro_id)) {
                    $fillData['bairro_id'] = $resolveRelacionamento(\App\Models\Bairro::class, $props->bairro_id);
                }
                if (isset($props->loteamento_id)) {
                    $fillData['loteamento_id'] = $resolveRelacionamento(\App\Models\Loteamento::class, $props->loteamento_id);
                }
                if (isset($props->quadra_id)) {
                    $fillData['quadra_id'] = $resolveRelacionamento(\App\Models\Quadra::class, $props->quadra_id);
                }
                if (isset($props->zona_id)) {
                    $fillData['zona_id'] = $resolveRelacionamento(\App\Models\Zona::class, $props->zona_id);
                }
                if (isset($props->lote_id)) {
                    $fillData['lote_id'] = $resolveRelacionamento(\App\Models\Lote::class, $props->lote_id);
                }

                // Demais propriedades
                if (isset($props->numero_lote) || isset($props->numero_lot) || isset($props->numero)) {
                    $fillData['numero_lote'] = $props->numero_lote ?? $props->numero_lot ?? $props->numero;
                }
                if (isset($props->area_geo)) {
                    $fillData['area_geo'] = $props->area_geo;
                }
                if (isset($props->main_facade_length)) {
                    $fillData['main_facade_length'] = $props->main_facade_length;
                }
                if (isset($props->tipo)) {
                    $fillData['tipo'] = $props->tipo;
                }
                if (isset($props->tp_construcao)) {
                    $fillData['tp_construcao'] = $props->tp_construcao;
                }
                if (isset($props->caracteristica_construcao)) {
                    $fillData['caracteristica_construcao'] = $props->caracteristica_construcao;
                }
                if (isset($props->estado_conservacao)) {
                    $fillData['estado_conservacao'] = $props->estado_conservacao;
                }
                if (isset($props->codigo_imovel_tributario)) {
                    $fillData['codigo_imovel_tributario'] = $props->codigo_imovel_tributario;
                }
                if (isset($props->inscricao_imobiliaria)) {
                    $fillData['inscricao_imobiliaria'] = $props->inscricao_imobiliaria;
                }

                } // fim do bloco legado (camadas não-Mob)

                // R67-1 / item 75 — CAMPOS CUSTOMIZADOS DO MUNICÍPIO
                // Property do GeoJSON com o mesmo nome do identificador do campo.
                // As colunas reais acima sempre vencem (o identificador é validado
                // contra a lista de colunas reservadas na criação do campo).
                if ($camposCustom->isNotEmpty()) {
                    $dadosCustom = [];

                    foreach ($camposCustom as $campoCustom) {
                        $slug = $campoCustom->slug;

                        if (! isset($props->{$slug}) || $props->{$slug} === '') {
                            continue;
                        }

                        $valor = $props->{$slug};

                        $dadosCustom[$slug] = match ($campoCustom->tipo) {
                            'numero' => is_numeric($valor) ? (float) $valor : null,
                            'sim_nao' => filter_var($valor, FILTER_VALIDATE_BOOLEAN),
                            'multipla' => is_array($valor) ? array_values($valor) : array_map('trim', explode(',', (string) $valor)),
                            default => is_array($valor) ? $valor : (string) $valor,
                        };

                        if ($dadosCustom[$slug] === null) {
                            unset($dadosCustom[$slug]);
                        }
                    }

                    if (! empty($dadosCustom)) {
                        $fillData['dados_customizados'] = $dadosCustom;
                    }
                }

                // 🛑 O PULO DO GATO: Guarda o ID que veio do JSON dentro do "sequential_id".
                // Deixamos a coluna primária "id" livre para o PostgreSQL gerar automaticamente e não dar erro Multi-Tenant.
                if (is_numeric($originalId)) {
                    $fillData['sequential_id'] = $originalId;
                }

                // MODO ATUALIZAR — o registro vivo de mesmo sequential_id é ATUALIZADO
                // (mesma PK ⇒ unidades, processos e coletas seguem apontando para ele).
                $existente = null;
                if ($modo === 'atualizar' && is_numeric($originalId)) {
                    $existente = $modelClass::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('sequential_id', $originalId)
                        ->whereNull('deleted_at')
                        ->first();
                }

                if ($existente) {
                    // Nunca troca a identidade do registro existente
                    unset($fillData['code'], $fillData['tenant_id']);

                    // Feature sem geometria não apaga a geometria já cadastrada
                    if (empty($item['type'])) {
                        unset($fillData['geo']);
                    }

                    // JSON sem 'name' não sobrescreve o nome existente com "Sem Nome"
                    if (($props->name ?? $props->numero_lote ?? null) === null) {
                        unset($fillData['name']);
                    }

                    // Campos custom fazem MERGE — property ausente no JSON fica como está
                    if (isset($fillData['dados_customizados'])) {
                        $fillData['dados_customizados'] = array_merge(
                            $existente->dados_customizados ?? [],
                            $fillData['dados_customizados']
                        );
                    }

                    $existente->forceFill($fillData)->save();
                    $atualizados++;
                } else {
                    // 4. SALVAR NO BANCO (modos adicionar/substituir e os novos do atualizar)
                    $entidade = new $modelClass;
                    $entidade->forceFill($fillData)->save();
                    $criados++;
                }
            }

            // MODO SUBSTITUIR — reconexão dos filhos: tudo que apontava para um registro
            // apagado NESTA operação passa a apontar para o novo de mesmo sequential_id.
            if ($modo === 'substituir' && $apagados > 0) {
                $reconectados = self::reconectarFilhos($modelClass, $tenant->id, $marcador);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // Mobilidade: extensão/área e azimute são CALCULADOS — o JSON nunca traz.
        // Recalcula a camada inteira do tenant (612 linhas = ms), assim a
        // importação já sai com os metadados sem depender do "Recalcular Áreas".
        self::recalcularMetadataMob($camada, $tenant->id);

        return [
            'total' => count($agrupados),
            'criados' => $criados,
            'atualizados' => $atualizados,
            'apagados' => $apagados,
            'reconectados' => $reconectados,
            'nao_resolvidos' => $naoResolvidos,
        ];
    }

    /**
     * Metadados geométricos das camadas mob_* após importar (docs/piuma.txt):
     * extensão (linhas) / área (zonas) e, em trechos e vias, o azimute do 1º ao
     * último vértice — mesma fórmula do gis:recalcular-metadata. Sobrescreve
     * (modo atualizar pode ter trocado a geometria). Tolerante a falha.
     */
    protected static function recalcularMetadataMob(string $camada, int $tenantId): void
    {
        $sql = match ($camada) {
            'MobTrecho', 'MobVia' => 'UPDATE '.($camada === 'MobVia' ? 'mob_vias' : 'mob_trechos').' SET
                    extensao_geo = ST_Length(geo::geography),
                    azimute = degrees(ST_Azimuth(
                        ST_StartPoint(ST_GeometryN(geo, 1)),
                        ST_EndPoint(ST_GeometryN(geo, ST_NumGeometries(geo)))
                    ))
                 WHERE tenant_id = ? AND geo IS NOT NULL',
            'MobEixo' => 'UPDATE mob_eixos SET extensao_geo = ST_Length(geo::geography) WHERE tenant_id = ? AND geo IS NOT NULL',
            'MobZona' => 'UPDATE mob_zonas SET area_geo = ST_Area(geo::geography) WHERE tenant_id = ? AND geo IS NOT NULL',
            default => null,
        };

        if ($sql === null) {
            return;
        }

        try {
            DB::update($sql, [$tenantId]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("ImportadorGis: metadados de {$camada} não recalculados — ".$e->getMessage());
        }
    }

    /**
     * Reconecta por sequential_id os filhos dos registros soft-deletados nesta operação.
     *
     * FKs que apontam para a tabela-pai são descobertas via information_schema — cobre
     * unidades, edificações, testadas, processos digitais, viabilidades, faces etc. sem
     * lista manual. A coleta_imobiliaria (polimórfica, sem FK) é tratada à parte.
     *
     * @return array<string,int> tabela filho => quantidade de vínculos reconectados
     */
    protected static function reconectarFilhos(string $modelClass, int $tenantId, Carbon $marcador): array
    {
        $tabelaPai = (new $modelClass)->getTable();
        $quando = $marcador->format('Y-m-d H:i:s');
        $reconectados = [];

        $fks = DB::select('
            SELECT DISTINCT tc.table_name AS tabela, kcu.column_name AS coluna
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON kcu.constraint_name = tc.constraint_name AND kcu.constraint_schema = tc.constraint_schema
            JOIN information_schema.constraint_column_usage ccu
              ON ccu.constraint_name = tc.constraint_name AND ccu.constraint_schema = tc.constraint_schema
            WHERE tc.constraint_type = \'FOREIGN KEY\'
              AND ccu.table_name = ?
              AND ccu.column_name = \'id\'
            ORDER BY 1, 2
        ', [$tabelaPai]);

        foreach ($fks as $fk) {
            // Nomes vêm do information_schema (catálogo do Postgres), não de input do usuário
            $afetados = DB::update("
                UPDATE {$fk->tabela} filho
                SET {$fk->coluna} = novo.id
                FROM {$tabelaPai} antigo
                JOIN {$tabelaPai} novo
                  ON novo.tenant_id = antigo.tenant_id
                 AND novo.sequential_id = antigo.sequential_id
                 AND novo.deleted_at IS NULL
                 AND novo.id <> antigo.id
                WHERE filho.{$fk->coluna} = antigo.id
                  AND antigo.tenant_id = ?
                  AND antigo.deleted_at = ?
            ", [$tenantId, $quando]);

            if ($afetados > 0) {
                $reconectados[$fk->tabela] = ($reconectados[$fk->tabela] ?? 0) + $afetados;
            }
        }

        // coleta_imobiliaria é polimórfica (coletavel_type/coletavel_id, sem FK)
        if (Schema::hasTable('coleta_imobiliaria')) {
            $afetados = DB::update("
                UPDATE coleta_imobiliaria filho
                SET coletavel_id = novo.id
                FROM {$tabelaPai} antigo
                JOIN {$tabelaPai} novo
                  ON novo.tenant_id = antigo.tenant_id
                 AND novo.sequential_id = antigo.sequential_id
                 AND novo.deleted_at IS NULL
                 AND novo.id <> antigo.id
                WHERE filho.coletavel_type = ?
                  AND filho.coletavel_id = antigo.id
                  AND antigo.tenant_id = ?
                  AND antigo.deleted_at = ?
            ", [$modelClass, $tenantId, $quando]);

            if ($afetados > 0) {
                $reconectados['coleta_imobiliaria'] = ($reconectados['coleta_imobiliaria'] ?? 0) + $afetados;
            }
        }

        return $reconectados;
    }

    /**
     * Mobilidade Urbana — colunas por camada (docs/piuma.txt §2). Só propriedades
     * presentes e não-vazias entram (o modo "atualizar" preserva o resto).
     */
    protected static function mapearCamposMob(string $camada, object $props, int $tenantId): array
    {
        $p = fn (string $campo) => (isset($props->{$campo}) && $props->{$campo} !== '') ? $props->{$campo} : null;
        $dados = [];

        $colunas = match ($camada) {
            'MobTrecho' => ['tipologia_da_via', 'tipo_de_pavimentacao', 'estado_conservacao_pavimentacao', 'dimensionamento_da_via'],
            'MobVia' => ['nome', 'sentido'], // sentido: mao_unica | mao_dupla (outro valor = ignorado)
            'MobPontoInteresse' => ['categoria', 'name', 'numero'],
            'MobEixo' => ['tipo', 'name'],
            'MobZona' => ['tipo', 'name', 'codigo', 'situacao', 'origens', 'destinos'],
            'MobFluxo' => ['destino'],
            default => [],
        };

        foreach ($colunas as $campo) {
            if ($p($campo) !== null) {
                $dados[$campo] = $p($campo);
            }
        }

        if ($camada === 'MobTrecho') {
            // O levantamento usa "classe_faixa_rodagem_da_via"
            $classe = $p('classe_faixa_rodagem') ?? $p('classe_faixa_rodagem_da_via');
            if ($classe !== null) {
                $dados['classe_faixa_rodagem'] = $classe;
            }
        }

        if ($camada === 'MobVia' && isset($dados['sentido'])
            && ! in_array($dados['sentido'], ['mao_unica', 'mao_dupla'], true)) {
            unset($dados['sentido']);
        }

        if ($camada === 'MobFluxo') {
            $dados['valores'] = (int) ($p('valores') ?? 0);
        }

        if ($camada === 'MobSinalizacao') {
            foreach (['descricao_original', 'observacao'] as $campo) {
                if ($p($campo) !== null) {
                    $dados[$campo] = $p($campo);
                }
            }
            // Decisão 6.1: a placa aponta pro CATÁLOGO — o JSON normalizado traz
            // o NOME do tipo (`tipo_sinalizacao`) + vertical/horizontal.
            $dados['tipo_sinalizacao_id'] = self::resolverTipoSinalizacao(
                $tenantId,
                $p('tipo_sinalizacao'),
                (string) ($p('tipo_sinalizacao_tipo') ?? 'vertical'),
            );
        }

        return $dados;
    }

    /** Cache por request: tenant|tipo|nome → id do catálogo. */
    protected static array $tiposSinalizacaoCache = [];

    /**
     * Nome do tipo → id no catálogo do tenant (case-insensitive). Sem
     * correspondência (ou sem nome) cai no tipo "A Classificar" do mesmo
     * tipo V/H — criado na hora se o catálogo não foi semeado.
     */
    protected static function resolverTipoSinalizacao(int $tenantId, ?string $nome, string $tipo): int
    {
        $tipo = strtolower($tipo) === 'horizontal' ? 'horizontal' : 'vertical';
        $chave = $tenantId.'|'.$tipo.'|'.mb_strtolower(trim((string) $nome));

        if (array_key_exists($chave, self::$tiposSinalizacaoCache)) {
            return self::$tiposSinalizacaoCache[$chave];
        }

        $id = null;
        if (filled($nome)) {
            $id = \App\Models\MobTipoSinalizacao::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('tipo', $tipo)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($nome))])
                ->value('id');
        }

        if (! $id) {
            $id = \App\Models\MobTipoSinalizacao::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => 'A Classificar', 'tipo' => $tipo],
                ['cor' => '#9CA3AF', 'ativo' => true],
            )->id;
        }

        return self::$tiposSinalizacaoCache[$chave] = $id;
    }
}
