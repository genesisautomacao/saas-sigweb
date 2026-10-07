<?php

namespace App\Services\ApiTools;

use App\Models\Tenant;
use App\Services\Fiscal\MapaFiscalService;
use Illuminate\Support\Facades\File;

/**
 * Integração com o sistema tributário da prefeitura — DESPACHANTE de dois modos:
 *
 *   simulação (padrão) → lê o JSON enviado pelo /admin (TenantResource → ação
 *     "Simulação Tributária"), salvo em storage/app/mocks/{slug}.json;
 *   produção → chama o DRIVER de API real do sistema (catálogo → coluna `driver`,
 *     registrado em self::DRIVERS) com as credenciais da prefeitura
 *     (tenant.data['tributario_api'] = ['url', 'token']).
 *
 * A CHAVE do modo é tenant.data['tributario_modo'] ('simulacao' | 'producao'),
 * configurada na seção "Integração Tributária" do TenantResource. Em produção,
 * falha da API = log + null — NUNCA cai no mock silenciosamente (uma demo antiga
 * viraria dado "real").
 *
 * R67-5 — este é o PONTO ÚNICO do de/para: o resultado (mock OU API) sai traduzido
 * pelo sistema tributário do município (MapaFiscalService), inclusive a BUSCA por
 * código no mock funciona quando o JSON usa os nomes de campo do sistema de origem.
 * Os consumidores (☁️ dos modais, Sincronizar do RelationManager, EditLote,
 * comando SincronizarTudoApi) não sabem a diferença entre os modos.
 *
 * Escala (Cajazeiras, 2026-10-07 — 39.705 imóveis × 21.818 unidades): o mock é lido
 * e decodificado UMA vez por processo e indexado por chave de busca (O(1)). Antes,
 * cada unidade relia 44 MB do disco e varria a lista inteira — horas de execução e
 * estouro do set_time_limit da ação do /admin. O arquivo aceita 3 formatos
 * (normalizarImoveis): array na raiz, {"imoveis": [...]} e GeoJSON FeatureCollection
 * (export do QGIS — os campos ficam em `properties`).
 */
class IntegraPrefeituraService
{
    /**
     * Registry dos conectores de API real: chave (coluna `driver` do catálogo)
     * => classe que implementa Drivers\TributarioDriver.
     * Vazio por enquanto — a 1ª entrada será o GOVBR (Bom Princípio/RS) quando
     * a prefeitura entregar credenciais + documentação da API.
     * Ex.: 'govbr' => \App\Services\ApiTools\Drivers\GovbrDriver::class,
     */
    public const DRIVERS = [];

    /**
     * Cache do mock por processo: [tenantId => ['caminho', 'mtime', 'imoveis', 'indices']].
     * `indices` = [chave => [valor => imóvel]], montado sob demanda por chave de busca.
     * Invalida sozinho quando o arquivo muda (mtime) ou via limparCacheMock().
     */
    protected static array $mockCache = [];

    /**
     * Busca um imóvel no sistema tributário usando o PONTO DE LIGAÇÃO do sistema.
     *
     * $identificadores = valores conhecidos da unidade, ex.:
     *   ['codigo_imovel_tributario' => '0000000182', 'inscricao_imobiliaria' => '01.02...']
     * O sistema do catálogo declara QUAL deles o localiza (`chave_ligacao`) —
     * uns fornecedores localizam pelo código do cadastro, outros pela inscrição.
     *
     * Devolve o payload já com as chaves canônicas preenchidas (bruto preservado).
     */
    public function buscarImovel(array $identificadores, $tenantId = null): ?array
    {
        // 1. Tenta descobrir o Tenant ID pelo Filament caso não tenha sido passado por parâmetro
        if (! $tenantId && class_exists('\Filament\Facades\Filament') && \Filament\Facades\Filament::getTenant()) {
            $tenantId = \Filament\Facades\Filament::getTenant()->id;
        }

        if (! $tenantId) {
            return null; // Sai silenciosamente se não souber de qual prefeitura é
        }

        $chave = self::chaveLigacao((int) $tenantId);
        $valor = $identificadores[$chave] ?? null;

        if (! filled($valor)) {
            return null; // A unidade não tem o identificador que este sistema exige
        }

        $imovel = self::modoProducao((int) $tenantId)
            ? self::buscarNaApi((int) $tenantId, $chave, (string) $valor)
            : self::buscarNoMock((int) $tenantId, $chave, (string) $valor);

        if (! $imovel) {
            return null;
        }

        // R67-5 — sai traduzido para o canônico (bruto preservado no próprio array)
        return MapaFiscalService::aplicar($imovel, (int) $tenantId);
    }

    /**
     * Compatibilidade: busca pelo código tributário. Prefira buscarImovel() com
     * todos os identificadores — se o sistema localizar por inscrição, esta
     * chamada devolve null.
     */
    public function buscarImovelPorCodigo(string $codigoTributario, $tenantId = null): ?array
    {
        return $this->buscarImovel(['codigo_imovel_tributario' => $codigoTributario], $tenantId);
    }

    /** Campo da unidade que localiza o imóvel neste sistema (padrão: código tributário). */
    public static function chaveLigacao(int $tenantId): string
    {
        return MapaFiscalService::sistemaDoTenant($tenantId)?->chave_ligacao
            ?? 'codigo_imovel_tributario';
    }

    /** A prefeitura está em modo PRODUÇÃO (API real) com driver disponível? */
    public static function modoProducao(int $tenantId): bool
    {
        $tenant = Tenant::find($tenantId);

        if (data_get($tenant?->data, 'tributario_modo') !== 'producao') {
            return false;
        }

        return self::driverDoTenant($tenantId) !== null;
    }

    /** Instancia o driver de API do sistema tributário da prefeitura (null = sem conector). */
    public static function driverDoTenant(int $tenantId): ?Drivers\TributarioDriver
    {
        $chave = MapaFiscalService::sistemaDoTenant($tenantId)?->driver;
        $classe = $chave ? (self::DRIVERS[$chave] ?? null) : null;

        return $classe ? app($classe) : null;
    }

    /** Busca na API real via driver. Falha = log + null (nunca cai no mock). */
    protected static function buscarNaApi(int $tenantId, string $chaveLigacao, string $valor): ?array
    {
        $tenant = Tenant::find($tenantId);
        $driver = self::driverDoTenant($tenantId);

        if (! $tenant || ! $driver) {
            return null;
        }

        try {
            $credenciais = (array) data_get($tenant->data, 'tributario_api', []);

            return $driver->buscarImovel($tenant, $credenciais, $chaveLigacao, $valor);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[IntegraPrefeitura] API real falhou', [
                'tenant' => $tenant->slug,
                'chave' => $chaveLigacao,
                'valor' => $valor,
                'erro' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Busca no JSON de simulação pelo índice em memória (respeitando o de/para
     * invertido na chave de busca). Vários imóveis com o mesmo valor da chave
     * (ex.: código do LOTE compartilhado por sublotes) ⇒ vale o PRIMEIRO do
     * arquivo — mesma regra do first() antigo; ordene o mock se quiser priorizar.
     */
    protected static function buscarNoMock(int $tenantId, string $chaveLigacao, string $valor): ?array
    {
        $imoveis = self::lerMock($tenantId);

        if ($imoveis === null) {
            return null;
        }

        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        // A chave de busca respeita o de/para: se o JSON usa o nome do sistema de
        // origem (ex.: cdImovel → codigo_imovel_tributario), procuramos pelos dois.
        foreach (self::chavesDeOrigem($tenantId, $chaveLigacao) as $chave) {
            if (! isset(self::$mockCache[$tenantId]['indices'][$chave])) {
                self::$mockCache[$tenantId]['indices'][$chave] = self::indexar($imoveis, $chave);
            }

            $indice = self::$mockCache[$tenantId]['indices'][$chave];

            if (isset($indice[$valor])) {
                return $indice[$valor];
            }
        }

        return null;
    }

    /**
     * Índice [valor da chave => imóvel] de uma lista de imóveis. Valor repetido:
     * o primeiro da lista vence. Puro (sem banco) — testável.
     */
    public static function indexar(array $imoveis, string $chave): array
    {
        $indice = [];

        foreach ($imoveis as $item) {
            if (! is_array($item) || ! isset($item[$chave])) {
                continue;
            }

            $valor = trim((string) $item[$chave]);

            if ($valor === '' || isset($indice[$valor])) {
                continue;
            }

            $indice[$valor] = $item;
        }

        return $indice;
    }

    /**
     * Normaliza o JSON de simulação para uma LISTA de imóveis (array assoc cada),
     * aceitando: array na raiz, {"imoveis": [...]} ou GeoJSON FeatureCollection
     * (os campos ficam em `properties`; a geometria é descartada). Só ESTRUTURA —
     * os valores saem exatamente como estão no arquivo, para os mocks já em produção
     * (Santa Cecília, Bom Princípio…) serem lidos igual a antes. A limpeza de
     * "NULL"/espaços (limparValores) é aplicada apenas no UPLOAD de arquivo novo.
     * Null = não é uma lista de imóveis.
     */
    public static function normalizarImoveis(mixed $json): ?array
    {
        if (is_object($json)) {
            $json = json_decode(json_encode($json), true);
        }

        if (! is_array($json)) {
            return null;
        }

        if (isset($json['features']) && is_array($json['features'])) {
            $lista = [];

            foreach ($json['features'] as $feature) {
                $props = is_array($feature) ? ($feature['properties'] ?? null) : null;

                if (is_array($props)) {
                    $lista[] = $props;
                }
            }
        } elseif (array_is_list($json)) {
            $lista = $json;
        } else {
            $lista = $json['imoveis'] ?? null;
        }

        if (! is_array($lista)) {
            return null;
        }

        $saida = [];

        foreach ($lista as $item) {
            if (is_array($item) && $item !== []) {
                $saida[] = $item;
            }
        }

        return $saida === [] ? null : $saida;
    }

    /**
     * Limpeza para arquivo NOVO (ação "Simulação Tributária"): apara strings; "" e
     * "NULL" (qualquer caixa) viram null — export de banco manda "NULL" literal, que
     * passaria pelo de/para como texto. Não mexe em números/arrays. Não é aplicada
     * na leitura, para não alterar o comportamento dos mocks já em produção.
     */
    public static function limparValores(array $item): array
    {
        foreach ($item as $chave => $valor) {
            if (is_string($valor)) {
                $valor = trim($valor);
                $item[$chave] = ($valor === '' || strcasecmp($valor, 'NULL') === 0) ? null : $valor;
            }
        }

        return $item;
    }

    /**
     * Rótulo da integração para as telas da PREFEITURA (transparência sem expor
     * a configuração): "Betha — produção (API)" | "Betha — simulação (JSON)".
     * Null = nada configurado (oculta).
     */
    public static function rotuloIntegracao(?int $tenantId): ?string
    {
        if (! $tenantId) {
            return null;
        }

        $sistema = MapaFiscalService::sistemaDoTenant($tenantId)?->nome;

        if (self::modoProducao($tenantId)) {
            return "{$sistema} — produção (API)";
        }

        $temMock = self::lerMock($tenantId) !== null;

        return match (true) {
            $sistema && $temMock => "{$sistema} — simulação (JSON)",
            $temMock => 'Simulação (JSON) — campos no padrão SIGWEB',
            (bool) $sistema => "{$sistema} — aguardando fonte de dados",
            default => null,
        };
    }

    /** Caminho do arquivo de simulação da prefeitura. */
    public static function caminhoMock(Tenant $tenant): string
    {
        return storage_path("app/mocks/{$tenant->slug}.json");
    }

    /**
     * Resumo do arquivo de simulação (para o status no /admin):
     * ['imoveis' => int, 'atualizado_em' => Carbon] ou null se não existe/ilegível.
     */
    public static function resumoMock(Tenant $tenant): ?array
    {
        $caminho = self::caminhoMock($tenant);

        if (! File::exists($caminho)) {
            return null;
        }

        $imoveis = self::lerMock((int) $tenant->id) ?? [];

        return [
            'imoveis' => count($imoveis),
            'atualizado_em' => \Illuminate\Support\Carbon::createFromTimestamp(File::lastModified($caminho)),
        ];
    }

    /** Esvazia o cache em memória (após trocar o arquivo pelo /admin ou em testes). */
    public static function limparCacheMock(): void
    {
        self::$mockCache = [];
    }

    /**
     * Lê e normaliza o mock do tenant UMA vez por processo (cache por mtime).
     * Null = sem arquivo ou arquivo que não é uma lista de imóveis.
     */
    protected static function lerMock(int $tenantId): ?array
    {
        $entrada = self::$mockCache[$tenantId] ?? null;
        $caminho = $entrada['caminho'] ?? null;

        if ($caminho === null) {
            $tenant = Tenant::find($tenantId);

            if (! $tenant) {
                return null;
            }

            $caminho = self::caminhoMock($tenant);
        }

        if (! File::exists($caminho)) {
            unset(self::$mockCache[$tenantId]);

            return null;
        }

        $mtime = File::lastModified($caminho);

        if ($entrada && ($entrada['mtime'] ?? null) === $mtime) {
            return $entrada['imoveis'];
        }

        $imoveis = self::normalizarImoveis(json_decode(File::get($caminho), true));

        self::$mockCache[$tenantId] = [
            'caminho' => $caminho,
            'mtime' => $mtime,
            'imoveis' => $imoveis,
            'indices' => [],
        ];

        return $imoveis;
    }

    /**
     * Nomes possíveis de um campo canônico no JSON: o próprio canônico + os nomes
     * de origem que o de/para do sistema aponta para ele.
     */
    protected static function chavesDeOrigem(int $tenantId, string $canonico): array
    {
        $chaves = [$canonico];

        foreach (MapaFiscalService::sistemaDoTenant($tenantId)?->mapa ?? [] as $origem => $destino) {
            if ($destino === $canonico) {
                $chaves[] = $origem;
            }
        }

        return $chaves;
    }
}
