<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Leitor do catálogo do mapa (config/mapa.php) — Release 83.
 *
 * Responde "este usuário pode VER / CRIAR / EDITAR / EXCLUIR esta entidade no
 * mapa?" e "pode usar esta ferramenta?". Regras:
 *  - módulo inativo na prefeitura = não, para todo mundo (D2, Manager incluído);
 *  - Master/Manager (flag roles.papel_sistema, global ou da prefeitura) = bypass;
 *  - demais = permissão do papel (User::temPermissao — falha fechada).
 * Entidade/ação desconhecida = não (falha fechada — o contrário do catálogo de
 * módulos, que trata o desconhecido como núcleo).
 */
final class MapaPermissoes
{
    public const ACOES = ['mapa', 'criar', 'editar', 'excluir'];

    private static ?array $indiceCamadas = null;

    private static ?array $indiceArtefatos = null;

    /** @var array<string, bool> cache de bypass por "user:tenant" na requisição */
    private static array $bypassCache = [];

    /** @var array<string, array<string, true>> permissões do usuário por "user:tenant" na requisição */
    private static array $permsCache = [];

    /**
     * Permissão do papel sem passar pelo Gate: `resumo()` roda a cada render do mapa e
     * cada `can()` dispararia a query crua do Gate::before (centenas por requisição).
     * O conjunto vem das relações do Spatie para o team id vigente, uma vez por usuário.
     */
    private static function temPermissaoNoTenant(User $user, string $perm, int $tenantId): bool
    {
        $chave = $user->id.':'.$tenantId;
        if (! isset(self::$permsCache[$chave])) {
            try {
                self::$permsCache[$chave] = $user->getAllPermissions()->pluck('name')->flip()->all();
            } catch (\Throwable) {
                self::$permsCache[$chave] = [];
            }
        }

        return isset(self::$permsCache[$chave][$perm]);
    }

    // ── Catálogo ─────────────────────────────────────────────────────────

    public static function entidades(): array
    {
        return (array) config('mapa.entidades', []);
    }

    public static function entidade(string $chave): ?array
    {
        return self::entidades()[$chave] ?? null;
    }

    public static function ferramentas(): array
    {
        return (array) config('mapa.ferramentas', []);
    }

    public static function ferramenta(string $id): ?array
    {
        return self::ferramentas()[$id] ?? null;
    }

    public static function permissaoFerramenta(string $id): string
    {
        return 'ferramenta_'.$id;
    }

    /** Nome da permissão de uma ação ('menu'|'mapa'|'criar'|'editar'|'excluir'); null = ação inexistente. */
    public static function permissao(string $entidade, string $acao): ?string
    {
        return self::entidade($entidade)[$acao] ?? null;
    }

    private static function normalizar(string $chave): string
    {
        return str_replace('-', '_', $chave);
    }

    /** Entidade dona de uma camada (data-layer / case do MapDataController); null = desconhecida. */
    public static function entidadeDaCamada(string $camada): ?string
    {
        if (self::$indiceCamadas === null) {
            self::$indiceCamadas = [];
            foreach (self::entidades() as $chave => $def) {
                foreach ((array) ($def['camadas'] ?? []) as $c) {
                    self::$indiceCamadas[self::normalizar((string) $c)] ??= $chave;
                }
            }
        }

        $c = self::normalizar($camada);

        return self::$indiceCamadas[$c] ?? (isset(self::entidades()[$c]) ? $c : null);
    }

    /** Entidade criada por um tipo do enableDrawing(); null = desconhecido. */
    public static function entidadeDoArtefato(string $artefato): ?string
    {
        if (self::$indiceArtefatos === null) {
            self::$indiceArtefatos = [];
            foreach (self::entidades() as $chave => $def) {
                foreach ((array) ($def['artefatos'] ?? []) as $a) {
                    self::$indiceArtefatos[(string) $a] ??= $chave;
                }
            }
        }

        return self::$indiceArtefatos[$artefato] ?? null;
    }

    /** Camadas servidas sem login pelo /api/gis-data (as do mapa público). */
    public static function camadaPublica(string $camada): bool
    {
        $ent = self::entidadeDaCamada($camada);

        return $ent !== null && (bool) (self::entidade($ent)['publica'] ?? false);
    }

    /** Todas as permissões declaradas no catálogo (entidades + ferramentas), sem repetir. */
    public static function todasPermissoes(): array
    {
        $todas = [];
        foreach (self::entidades() as $def) {
            foreach (['menu', 'mapa', 'criar', 'editar', 'excluir'] as $acao) {
                if (! empty($def[$acao])) {
                    $todas[$def[$acao]] = true;
                }
            }
        }
        foreach (array_keys(self::ferramentas()) as $id) {
            $todas[self::permissaoFerramenta($id)] = true;
        }

        return array_keys($todas);
    }

    // ── Estado do usuário ────────────────────────────────────────────────

    /**
     * Bypass de Master (papel global) ou Manager da prefeitura — mesma regra do
     * Gate::before, por FLAG (D7), em query crua (ignora o cache do Spatie).
     */
    public static function bypass(?User $user, ?int $tenantId): bool
    {
        if (! $user) {
            return false;
        }
        $chave = $user->id.':'.(int) $tenantId;
        if (array_key_exists($chave, self::$bypassCache)) {
            return self::$bypassCache[$chave];
        }

        return self::$bypassCache[$chave] = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', $user::class)
            ->whereIn('roles.papel_sistema', ['master', 'manager'])
            ->where(fn ($w) => $w->whereNull('roles.tenant_id')->orWhere('roles.tenant_id', (int) $tenantId))
            ->exists();
    }

    /**
     * Fora do Filament (rotas /api/*) o team id do Spatie não está setado: sem isto
     * `temPermissao()` não enxerga os papéis da prefeitura. Devolve false quando o
     * usuário não é equipe da prefeitura (cidadão ou sem vínculo).
     */
    public static function vincularEquipe(?User $user, int $tenantId): bool
    {
        if (! $user || $user->isCidadao() || ! $user->tenants()->whereKey($tenantId)->exists()) {
            return false;
        }
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        unset(self::$permsCache[$user->id.':'.$tenantId]);

        return true;
    }

    private static function resolver(?User $user, ?Tenant $tenant): array
    {
        $user ??= auth()->user();
        $tenant ??= Modulos::tenantAtual();

        return [$user instanceof User ? $user : null, $tenant];
    }

    /** Filhos do lote (edificação, testada, unidade, fotos): qualquer escrita = EDITAR o lote. */
    public static function filhos(): array
    {
        return (array) config('mapa.filhos', []);
    }

    /** O usuário pode executar a ação ('mapa'|'criar'|'editar'|'excluir') nesta entidade/camada/artefato? */
    public static function pode(string $entidade, string $acao, ?User $user = null, ?Tenant $tenant = null): bool
    {
        [$user, $tenant] = self::resolver($user, $tenant);
        if (! $user || ! $tenant) {
            return false;
        }

        if (! self::entidade($entidade) && isset(self::filhos()[$entidade])) {
            $entidade = self::filhos()[$entidade];
            $acao = $acao === 'mapa' ? 'mapa' : 'editar';
        }

        $chave = self::entidade($entidade) ? $entidade : (self::entidadeDaCamada($entidade) ?? self::entidadeDoArtefato($entidade));
        $def = $chave ? self::entidade($chave) : null;
        if (! $def) {
            return false;
        }
        if (! Modulos::ativo((string) ($def['modulo'] ?? Modulos::NUCLEO), $tenant)) {
            return false;
        }
        $perm = $def[$acao] ?? null;
        if (! $perm) {
            return false;
        }
        if (self::bypass($user, $tenant->id)) {
            return true;
        }

        return self::temPermissaoNoTenant($user, $perm, (int) $tenant->id);
    }

    /** Pode usar a ferramenta (permissão própria + módulo + o que ela exige da entidade)? */
    public static function podeFerramenta(string $id, ?User $user = null, ?Tenant $tenant = null): bool
    {
        [$user, $tenant] = self::resolver($user, $tenant);
        $def = self::ferramenta($id);
        if (! $user || ! $tenant || ! $def) {
            return false;
        }
        if (! Modulos::ferramentaDisponivel($id, $tenant)) {
            return false;
        }
        foreach ((array) ($def['exige'] ?? []) as $entidade => $acao) {
            if (! self::pode((string) $entidade, (string) $acao, $user, $tenant)) {
                return false;
            }
        }
        if (self::bypass($user, $tenant->id)) {
            return true;
        }

        return self::temPermissaoNoTenant($user, self::permissaoFerramenta($id), (int) $tenant->id);
    }

    /** Tem permissão de criar ALGUMA entidade (mostra o botão "Criar Artefatos")? */
    public static function criaAlgo(?User $user = null, ?Tenant $tenant = null): bool
    {
        foreach (array_keys(self::entidades()) as $chave) {
            if (self::pode($chave, 'criar', $user, $tenant)) {
                return true;
            }
        }

        return false;
    }

    /** Tem alguma das ferramentas (mostra o menu)? */
    public static function algumaFerramenta(array $ids, ?User $user = null, ?Tenant $tenant = null): bool
    {
        foreach ($ids as $id) {
            if (self::podeFerramenta((string) $id, $user, $tenant)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pode receber os DADOS da camada (/api/gis-data, consulta avançada, SHP)?
     * Camada pública = qualquer um (mapa do cidadão). Demais = equipe vinculada à
     * prefeitura com `mapa` da entidade (ou bypass). Camada desconhecida = só equipe.
     * Chame vincularEquipe() antes, nas rotas fora do Filament.
     */
    public static function podeVerCamada(string $camada, ?User $user, Tenant $tenant): bool
    {
        if (self::camadaPublica($camada)) {
            return Modulos::camadaDisponivel($camada, $tenant);
        }
        if (! $user || $user->isCidadao() || ! $user->tenants()->whereKey($tenant->id)->exists()) {
            return false;
        }
        $ent = self::entidadeDaCamada($camada);
        if ($ent === null) {
            return Modulos::camadaDisponivel($camada, $tenant);
        }

        return self::pode($ent, 'mapa', $user, $tenant);
    }

    /**
     * Resumo para o blade/engine: por entidade {mapa, criar, editar, excluir}, por
     * camada (chave data-layer, com "_" e com "-") se pode ver, por artefato se pode
     * criar, e por ferramenta.
     */
    public static function resumo(?User $user = null, ?Tenant $tenant = null): array
    {
        [$user, $tenant] = self::resolver($user, $tenant);
        $out = ['bypass' => false, 'entidades' => [], 'camadas' => [], 'artefatos' => [], 'ferramentas' => []];
        if (! $user || ! $tenant) {
            return $out;
        }
        $out['bypass'] = self::bypass($user, $tenant->id);

        foreach (self::entidades() as $chave => $def) {
            $acoes = [];
            foreach (self::ACOES as $acao) {
                $acoes[$acao] = self::pode($chave, $acao, $user, $tenant);
            }
            $out['entidades'][$chave] = $acoes;
            foreach ((array) ($def['camadas'] ?? []) as $c) {
                // filhos (edificacoes…) passam por pode() para herdar a regra do lote
                $podeVer = self::pode((string) $c, 'mapa', $user, $tenant);
                $out['camadas'][(string) $c] = $podeVer;
                $out['camadas'][str_replace('_', '-', (string) $c)] = $podeVer;
            }
            foreach ((array) ($def['artefatos'] ?? []) as $a) {
                $out['artefatos'][(string) $a] = self::pode((string) $a, 'criar', $user, $tenant);
            }
        }
        foreach (array_keys(self::ferramentas()) as $id) {
            $out['ferramentas'][$id] = self::podeFerramenta($id, $user, $tenant);
        }

        return $out;
    }

    /** Limpa os caches estáticos (testes e comandos que trocam de usuário/tenant). */
    public static function limparCache(): void
    {
        self::$indiceCamadas = null;
        self::$indiceArtefatos = null;
        self::$bypassCache = [];
        self::$permsCache = [];
    }
}
