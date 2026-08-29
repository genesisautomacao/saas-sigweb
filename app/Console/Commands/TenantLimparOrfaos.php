<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Saas\TenantExclusaoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Limpeza de ÓRFÃOS de prefeituras já excluídas (2026-08-27).
 *
 * Antes da ação "Excluir prefeitura (definitivo)", apagar um tenant deixava
 * para trás: usuários sem vínculo, linhas em tabelas sem FK (viabilidades),
 * papéis Spatie do time, mock tributário, pasta de nuvem de pontos e o
 * prefixo do bucket R2. Este comando encontra e limpa esses restos.
 *
 * SEGURO POR PADRÃO: sem --executar, apenas SIMULA (mostra o que faria).
 *
 *   php artisan tenant:limpar-orfaos             # simulação (não apaga nada)
 *   php artisan tenant:limpar-orfaos --executar  # executa a limpeza
 */
class TenantLimparOrfaos extends Command
{
    protected $signature = 'tenant:limpar-orfaos {--executar : Executa a limpeza (sem esta flag, apenas simula)}';

    protected $description = 'Encontra e limpa registros, usuários e arquivos de prefeituras que não existem mais';

    public function handle(): int
    {
        $executar = (bool) $this->option('executar');
        $ids = Tenant::pluck('id');
        $slugs = Tenant::pluck('slug')->all();

        $this->info(($executar ? '🧹 EXECUTANDO limpeza' : '🔎 SIMULAÇÃO (nada será apagado)')
            .' — '.count($slugs).' prefeitura(s) existente(s).');
        $this->newLine();

        // ── 1. Linhas órfãs em qualquer tabela com tenant_id ─────────────────
        $totalLinhas = 0;
        foreach (TenantExclusaoService::tabelasComTenantId() as $tabela) {
            $query = DB::table($tabela)->whereNotNull('tenant_id')->whereNotIn('tenant_id', $ids);
            $n = $query->count();
            if ($n === 0) {
                continue;
            }
            $totalLinhas += $n;
            $this->line("  • {$tabela}: {$n} linha(s) órfã(s)".($executar ? ' → apagadas' : ''));
            if ($executar) {
                $query->delete();
            }
        }
        $totalLinhas === 0
            ? $this->info('  ✓ Nenhuma linha órfã nas tabelas com tenant_id.')
            : $this->warn("  Total: {$totalLinhas} linha(s) órfã(s).");
        $this->newLine();

        // ── 2. Usuários órfãos (sem NENHUM vínculo e sem papel global) ───────
        $comVinculo = DB::table('tenant_user')->distinct()->pluck('user_id');
        $globais = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereNull('roles.tenant_id')
            ->where('model_has_roles.model_type', User::class)
            ->distinct()
            ->pluck('model_has_roles.model_id');

        $orfaos = DB::table('users')
            ->whereNotIn('id', $comVinculo->merge($globais)->unique())
            ->get(['id', 'name', 'email']);

        if ($orfaos->isEmpty()) {
            $this->info('  ✓ Nenhum usuário órfão.');
        } else {
            foreach ($orfaos as $u) {
                $this->line("  • Usuário órfão #{$u->id}: {$u->name} <{$u->email}>".($executar ? ' → excluído' : ''));
            }
            if ($executar) {
                TenantExclusaoService::excluirUsuarios($orfaos->pluck('id')->all());
            }
            $this->warn('  Total: '.$orfaos->count().' usuário(s) órfão(s).');
        }
        $this->newLine();

        // ── 3. Mocks tributários órfãos (storage/app/mocks/{slug}.json) ──────
        $mocksDir = storage_path('app/mocks');
        $mocksOrfaos = 0;
        if (is_dir($mocksDir)) {
            foreach (File::files($mocksDir) as $arquivo) {
                $slug = $arquivo->getFilenameWithoutExtension();
                if ($arquivo->getExtension() === 'json' && ! in_array($slug, $slugs, true)) {
                    $mocksOrfaos++;
                    $this->line("  • Mock órfão: mocks/{$slug}.json".($executar ? ' → apagado' : ''));
                    if ($executar) {
                        File::delete($arquivo->getPathname());
                    }
                }
            }
        }
        if ($mocksOrfaos === 0) {
            $this->info('  ✓ Nenhum mock tributário órfão.');
        }
        $this->newLine();

        // ── 4. Nuvem de pontos órfã (public/nuvem-pontos/{slug}) ─────────────
        $potreeDir = public_path('nuvem-pontos');
        $potreeOrfaos = 0;
        if (is_dir($potreeDir)) {
            foreach (File::directories($potreeDir) as $dir) {
                $slug = basename($dir);
                if (! in_array($slug, $slugs, true)) {
                    $potreeOrfaos++;
                    $this->line("  • Nuvem de pontos órfã: nuvem-pontos/{$slug}/".($executar ? ' → removida' : ''));
                    if ($executar) {
                        File::deleteDirectory($dir);
                    }
                }
            }
        }
        if ($potreeOrfaos === 0) {
            $this->info('  ✓ Nenhuma pasta de nuvem de pontos órfã.');
        }
        $this->newLine();

        // ── 5. Prefixos órfãos no bucket R2 (best-effort) ────────────────────
        if (config('filesystems.disks.midia.key')) {
            try {
                $prefixosOrfaos = collect(Storage::disk('midia')->directories(''))
                    ->reject(fn ($p) => in_array(basename($p), $slugs, true));

                if ($prefixosOrfaos->isEmpty()) {
                    $this->info('  ✓ Nenhum prefixo órfão no bucket R2.');
                } else {
                    foreach ($prefixosOrfaos as $prefixo) {
                        $this->line("  • R2 órfão: {$prefixo}/".($executar ? ' → removido' : ''));
                        if ($executar) {
                            Storage::disk('midia')->deleteDirectory($prefixo);
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('  ⚠ R2 inacessível: '.$e->getMessage());
            }
        } else {
            $this->comment('  – Bucket R2 sem credenciais neste ambiente (pulado).');
        }

        $this->newLine();
        $executar
            ? $this->info('✅ Limpeza concluída.')
            : $this->comment('Nada foi apagado. Rode com --executar para aplicar a limpeza acima.');

        return self::SUCCESS;
    }
}
