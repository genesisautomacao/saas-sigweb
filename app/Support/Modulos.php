<?php

namespace App\Support;

use App\Models\Tenant;
use Filament\Facades\Filament;

/**
 * Leitor do catálogo de módulos (config/modulos.php) — docs/Modulos_Permissoes.txt.
 *
 * Responde "isto pertence a que módulo?" e "este módulo está ativo nesta
 * prefeitura?" para menu, papéis, mapa e /admin. O tenant padrão é o da sessão
 * Filament; fora dela (console/API) passe o Tenant explicitamente.
 *
 * Regra de ouro: o que o catálogo NÃO conhece (permissão, camada, artefato ou
 * ferramenta sem módulo) é tratado como núcleo — nunca esconder por engano.
 */
final class Modulos
{
    public const NUCLEO = 'nucleo';

    private static ?array $catalogo = null;

    /** permissão => módulo, camada => módulo … (índices montados uma vez) */
    private static ?array $indice = null;

    public static function catalogo(): array
    {
        return self::$catalogo ??= (array) config('modulos', []);
    }

    /** Chaves contratáveis (sem o núcleo), na ordem do catálogo. */
    public static function chaves(): array
    {
        return array_values(array_filter(array_keys(self::catalogo()), fn ($k) => $k !== self::NUCLEO));
    }

    public static function label(string $chave): string
    {
        return self::catalogo()[$chave]['label'] ?? $chave;
    }

    /** chave => rótulo, para o Select de módulos do TenantResource. */
    public static function opcoesTenant(): array
    {
        $opcoes = [];
        foreach (self::chaves() as $chave) {
            $opcoes[$chave] = self::label($chave);
        }

        return $opcoes;
    }

    /** Pré-requisitos declarados (`requer`) de um módulo. */
    public static function requer(string $chave): array
    {
        return (array) (self::catalogo()[$chave]['requer'] ?? []);
    }

    /**
     * Módulos marcados cujo pré-requisito não está marcado: ['coleta_cadastral' => ['imobiliario']].
     * Usado no aviso do /admin (nada é bloqueado — decisão D4).
     */
    public static function requisitosFaltantes(array $modules): array
    {
        $faltando = [];
        foreach ($modules as $chave) {
            $ausentes = array_values(array_diff(self::requer((string) $chave), $modules));
            if ($ausentes) {
                $faltando[$chave] = $ausentes;
            }
        }

        return $faltando;
    }

    // ── Estado da prefeitura ────────────────────────────────────────────

    public static function tenantAtual(): ?Tenant
    {
        try {
            $tenant = Filament::getTenant();
        } catch (\Throwable) {
            $tenant = null;
        }

        return $tenant instanceof Tenant ? $tenant : null;
    }

    /** Módulos ativos da prefeitura (só chaves conhecidas). O núcleo não entra na lista. */
    public static function ativos(?Tenant $tenant = null): array
    {
        $tenant ??= self::tenantAtual();
        if (! $tenant) {
            return [];
        }
        $conhecidas = self::chaves();

        return array_values(array_intersect((array) ($tenant->modules ?? []), $conhecidas));
    }

    public static function ativo(string $chave, ?Tenant $tenant = null): bool
    {
        if ($chave === self::NUCLEO || $chave === '') {
            return true;
        }

        return in_array($chave, self::ativos($tenant), true);
    }

    public static function algumAtivo(array $chaves, ?Tenant $tenant = null): bool
    {
        foreach ($chaves as $chave) {
            if (self::ativo((string) $chave, $tenant)) {
                return true;
            }
        }

        return false;
    }

    // ── Índices (o que pertence a quem) ─────────────────────────────────

    private static function indice(string $tipo): array
    {
        if (self::$indice === null) {
            self::$indice = ['permissoes' => [], 'camadas' => [], 'artefatos' => [], 'ferramentas' => []];
            foreach (self::catalogo() as $chave => $def) {
                foreach (['permissoes', 'camadas', 'artefatos', 'ferramentas'] as $t) {
                    foreach ((array) ($def[$t] ?? []) as $item) {
                        self::$indice[$t][self::normalizar((string) $item)] ??= $chave;
                    }
                }
            }
        }

        return self::$indice[$tipo] ?? [];
    }

    private static function normalizar(string $chave): string
    {
        return str_replace('-', '_', $chave);
    }

    /** Módulo dono da permissão; 'nucleo' p/ as do núcleo; null = desconhecida. */
    public static function daPermissao(string $permissao): ?string
    {
        return self::indice('permissoes')[$permissao] ?? null;
    }

    public static function permissaoDisponivel(string $permissao, ?Tenant $tenant = null): bool
    {
        $modulo = self::daPermissao($permissao);

        return $modulo === null || self::ativo($modulo, $tenant);
    }

    /** Filtra um array `permissao => rótulo` deixando só as de módulos ativos (ou desconhecidas). */
    public static function filtrarOpcoes(array $opcoes, ?Tenant $tenant = null): array
    {
        return array_filter($opcoes, fn ($rotulo, $perm) => self::permissaoDisponivel((string) $perm, $tenant), ARRAY_FILTER_USE_BOTH);
    }

    /** Todas as permissões de módulos INATIVOS na prefeitura (p/ preservar/ocultar). */
    public static function permissoesInativas(?Tenant $tenant = null): array
    {
        $inativas = [];
        foreach (self::catalogo() as $chave => $def) {
            if ($chave === self::NUCLEO || self::ativo($chave, $tenant)) {
                continue;
            }
            foreach ((array) ($def['permissoes'] ?? []) as $p) {
                $inativas[$p] = true;
            }
        }

        return array_keys($inativas);
    }

    public static function daCamada(string $camada): ?string
    {
        return self::indice('camadas')[self::normalizar($camada)] ?? null;
    }

    public static function camadaDisponivel(string $camada, ?Tenant $tenant = null): bool
    {
        $modulo = self::daCamada($camada);

        return $modulo === null || self::ativo($modulo, $tenant);
    }

    /** camada => módulo (chaves com "_"), para o engine do mapa. */
    public static function mapaCamadas(): array
    {
        return self::indice('camadas');
    }

    /** Nomes usados nos filtros/consultas que diferem da chave da camada. */
    private const CAMADA_ALIAS = [
        'perimetros_urbanos' => 'perimetros',
        'patrimonio_publico' => 'patrimonio_publicos',
    ];

    /** Filtra um array `camada => rótulo` (Selects do filtro avançado, "Salvar como"). */
    public static function filtrarCamadas(array $opcoes, ?Tenant $tenant = null): array
    {
        return array_filter(
            $opcoes,
            fn ($rotulo, $camada) => self::camadaDisponivel(self::CAMADA_ALIAS[$camada] ?? (string) $camada, $tenant),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** Camadas do painel cujo módulo está inativo (p/ o endpoint map-permissions). */
    public static function camadasIndisponiveis(?Tenant $tenant = null): array
    {
        return array_keys(array_filter(self::mapaCamadas(), fn ($modulo) => ! self::ativo($modulo, $tenant)));
    }

    public static function daArtefato(string $entidade): ?string
    {
        return self::indice('artefatos')[$entidade] ?? null;
    }

    public static function artefatoDisponivel(string $entidade, ?Tenant $tenant = null): bool
    {
        $modulo = self::daArtefato($entidade);

        return $modulo === null || self::ativo($modulo, $tenant);
    }

    public static function daFerramenta(string $id): ?string
    {
        return self::indice('ferramentas')[$id] ?? null;
    }

    public static function ferramentaDisponivel(string $id, ?Tenant $tenant = null): bool
    {
        $modulo = self::daFerramenta($id);

        return $modulo === null || self::ativo($modulo, $tenant);
    }

    /** Limpa os caches estáticos (testes e comandos que trocam de tenant). */
    public static function limparCache(): void
    {
        self::$catalogo = null;
        self::$indice = null;
    }
}
