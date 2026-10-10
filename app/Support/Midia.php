<?php

namespace App\Support;

use App\Models\Tenant;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Arquivos enviados ao sistema (anexos, documentos, fotos de rua, 360 avulsas) nos
 * buckets do Cloudflare R2 — INF-2, Release 82 (2026-10-10). Generaliza o FotosLote.
 *
 * Onde está cada arquivo é decidido PELO CAMINHO GRAVADO, sem consultar o bucket:
 *  - `{tenant_slug}/{pasta}/...` → bucket (o disco depende da pasta, ver PASTAS):
 *      "midia" = privado `sigweb-midia` (link temporário) · "fotos" = público `sigweb-fotos`;
 *  - `{pasta}/...` (legado) → disk "public" da VPS (/storage/{caminho}).
 *
 * Arquivo novo vai para o bucket quando há credencial e slug; se o bucket recusar
 * (sem credencial, chave só leitura no servidor de testes, falha de rede), cai no disk
 * "public" como sempre foi. A leitura dupla é permanente.
 *
 * Privado = o link temporário só é gerado para quem já pode ver a tela (Filament/policies);
 * para links que precisam ser estáveis (PDF do processo), use a rota processo.anexo.abrir.
 */
final class Midia
{
    /** Pasta (como aparece depois do slug) => disco do bucket. A mais específica primeiro. */
    public const PASTAS = [
        'unidades_imobiliarias/documentos' => 'midia',
        'secoes_logradouro/fotos' => 'fotos',
        'chamados/fotos' => 'midia',
        'processos_anexos' => 'midia',
        'documentos' => 'midia',
        'chamados_fotos' => 'midia',
        'panoramicas' => 'midia',
        'solicitacoes_fotos' => 'fotos',
        'os_fotos' => 'fotos',
    ];

    public const DISCO_LOCAL = 'public';

    /** Validade do link temporário de arquivo privado (a página pode ficar aberta um tempo). */
    public const MINUTOS_LINK = 120;

    /** Credenciais do disco carregadas neste ambiente? */
    public static function bucketAtivo(string $disco): bool
    {
        return filled(config("filesystems.disks.{$disco}.key"))
            && filled(config("filesystems.disks.{$disco}.endpoint"));
    }

    /** Disco do bucket do caminho (`midia`|`fotos`) ou null se for caminho legado da VPS. */
    public static function discoBucket(?string $caminho): ?string
    {
        if (blank($caminho)) {
            return null;
        }

        $caminho = ltrim($caminho, '/');
        foreach (self::PASTAS as $pasta => $disco) {
            if (preg_match('#^[a-z0-9][a-z0-9-]*/'.preg_quote($pasta, '#').'/#', $caminho) === 1) {
                return $disco;
            }
        }

        return null;
    }

    public static function noBucket(?string $caminho): bool
    {
        return self::discoBucket($caminho) !== null;
    }

    public static function disco(?string $caminho): Filesystem
    {
        return Storage::disk(self::discoBucket($caminho) ?? self::DISCO_LOCAL);
    }

    /**
     * URL para exibir/baixar. Público = URL fixa; privado = link temporário (memorizado
     * para não assinar de novo a cada render); legado = /storage.
     */
    public static function url(?string $caminho): ?string
    {
        if (blank($caminho)) {
            return null;
        }

        $caminho = ltrim($caminho, '/');
        $disco = self::discoBucket($caminho);

        if ($disco === 'fotos') {
            return rtrim((string) config('filesystems.disks.fotos.url'), '/').'/'.$caminho;
        }

        if ($disco === 'midia') {
            try {
                return Cache::remember('midia-url:'.md5($caminho), now()->addMinutes(self::MINUTOS_LINK - 20),
                    fn () => Storage::disk('midia')->temporaryUrl($caminho, now()->addMinutes(self::MINUTOS_LINK)));
            } catch (\Throwable $e) {
                Log::warning("[Midia] falha ao assinar {$caminho}: ".$e->getMessage());

                return null;
            }
        }

        return asset('storage/'.$caminho);
    }

    /** Bytes do arquivo (null se não existir / bucket inacessível). */
    public static function conteudo(?string $caminho): ?string
    {
        if (blank($caminho)) {
            return null;
        }

        try {
            $conteudo = self::disco($caminho)->get(ltrim($caminho, '/'));

            return $conteudo === null || $conteudo === false || $conteudo === '' ? null : $conteudo;
        } catch (\Throwable $e) {
            Log::warning("[Midia] falha ao ler {$caminho}: ".$e->getMessage());

            return null;
        }
    }

    public static function excluir(?string $caminho): void
    {
        if (blank($caminho)) {
            return;
        }

        try {
            self::disco($caminho)->delete(ltrim($caminho, '/'));
        } catch (\Throwable $e) {
            Log::warning("[Midia] falha ao apagar {$caminho}: ".$e->getMessage());
        }
    }

    /** Slug da prefeitura: Tenant, id, slug pronto, closure ou o tenant do painel. */
    public static function slug(Tenant|Closure|int|string|null $tenant = null): ?string
    {
        if ($tenant instanceof Closure) {
            $tenant = $tenant();
        }

        return match (true) {
            $tenant instanceof Tenant => $tenant->slug,
            is_int($tenant) || (is_string($tenant) && ctype_digit($tenant)) => Tenant::query()->whereKey((int) $tenant)->value('slug'),
            is_string($tenant) && $tenant !== '' => $tenant,
            default => Filament::getTenant()?->slug,
        };
    }

    /**
     * Grava bytes e devolve o caminho a guardar no banco. Bucket recusou ⇒ VPS.
     * $nome nulo = ULID + extensão.
     */
    public static function salvar(string $conteudo, string $pasta, ?string $slug, string $extensao = 'bin', ?string $nome = null): string
    {
        $nome ??= Str::ulid().'.'.(strtolower(preg_replace('/[^a-z0-9]/i', '', $extensao)) ?: 'bin');
        $disco = self::discoDaPasta($pasta);

        if ($disco && filled($slug) && self::bucketAtivo($disco)) {
            $caminho = $slug.'/'.$pasta.'/'.$nome;
            try {
                if (Storage::disk($disco)->put($caminho, $conteudo)) {
                    return $caminho;
                }
            } catch (\Throwable $e) {
                // cai na VPS abaixo
            }
            Log::warning("[Midia] bucket {$disco} recusou {$caminho}; gravado na VPS.");
        }

        $caminho = self::pastaLocal($pasta).'/'.$nome;
        Storage::disk(self::DISCO_LOCAL)->put($caminho, $conteudo);

        return $caminho;
    }

    /** Grava um upload do Livewire (FileUpload) — mesma regra do salvar(). */
    public static function salvarUpload(TemporaryUploadedFile $arquivo, string $pasta, ?string $slug, bool $manterNome = false): string
    {
        $extensao = strtolower($arquivo->getClientOriginalExtension()) ?: 'bin';
        // Nome original preservado numa subpasta única: o download sai com o nome do
        // arquivo e dois envios de mesmo nome nunca se sobrescrevem.
        $nome = $manterNome
            ? Str::ulid().'/'.self::nomeSeguro($arquivo->getClientOriginalName() ?: 'arquivo.'.$extensao)
            : Str::ulid().'.'.$extensao;

        $disco = self::discoDaPasta($pasta);
        if ($disco && filled($slug) && self::bucketAtivo($disco)) {
            $caminho = $slug.'/'.$pasta.'/'.$nome;
            try {
                $stream = fopen($arquivo->getRealPath(), 'r');
                $ok = Storage::disk($disco)->writeStream($caminho, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if ($ok) {
                    return $caminho;
                }
            } catch (\Throwable $e) {
                // cai na VPS abaixo
            }
            Log::warning("[Midia] bucket {$disco} recusou {$caminho}; gravado na VPS.");
        }

        $caminho = self::pastaLocal($pasta).'/'.$nome;
        Storage::disk(self::DISCO_LOCAL)->putFileAs(dirname($caminho), $arquivo, basename($caminho));

        return $caminho;
    }

    /**
     * Configura um FileUpload para gravar via Midia (macro ->midia()):
     *  - upload novo vai para o bucket da pasta (ou para a VPS, com fallback);
     *  - caminho já gravado NUNCA é descartado na hidratação (o Filament filtra o que
     *    não existe no disk do campo) e a prévia/download usa a URL certa de cada caminho.
     *
     * @param  Tenant|Closure|int|string|null  $tenant  resolvido na hora de salvar
     */
    public static function campoUpload(FileUpload $campo, string $pasta, Tenant|Closure|int|string|null $tenant = null): FileUpload
    {
        return $campo
            ->disk(self::DISCO_LOCAL)
            ->directory(self::pastaLocal($pasta))
            ->fetchFileInformation(false)
            ->getUploadedFileUsing(fn (string $file) => [
                'name' => basename($file),
                'size' => 0,
                'type' => null,
                'url' => self::url($file),
            ])
            ->saveUploadedFileUsing(fn (FileUpload $component, TemporaryUploadedFile $file) => self::salvarUpload(
                $file, $pasta, self::slugDoCampo($component, $tenant), $component->shouldPreserveFilenames()
            ));
    }

    /**
     * Slug na hora de salvar: o informado no campo > tenant do painel > tenant do registro
     * do formulário (painel do cidadão não tem tenancy, mas o processo tem tenant_id).
     */
    private static function slugDoCampo(FileUpload $component, Tenant|Closure|int|string|null $tenant): ?string
    {
        $slug = self::slug($tenant);
        if (filled($slug)) {
            return $slug;
        }

        $tenantId = $component->getRecord()?->tenant_id ?? null;

        return $tenantId ? self::slug((int) $tenantId) : null;
    }

    /** Disco do bucket de uma pasta de destino (aceita subpasta: "panoramicas/avulsas"). */
    public static function discoDaPasta(string $pasta): ?string
    {
        foreach (self::PASTAS as $chave => $disco) {
            if ($pasta === $chave || str_starts_with($pasta, $chave.'/')) {
                return $disco;
            }
        }

        return null;
    }

    /** Pasta legada na VPS para uma pasta do bucket (as 360 avulsas sempre foram "panoramas"). */
    public static function pastaLocal(string $pasta): string
    {
        return str_starts_with($pasta, 'panoramicas') ? 'panoramas' : $pasta;
    }

    private static function nomeSeguro(string $nome): string
    {
        $base = pathinfo($nome, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        $base = trim(preg_replace('/[^A-Za-z0-9._ -]+/', '', Str::ascii($base))) ?: 'arquivo';

        return Str::limit($base, 120, '').($ext !== '' ? '.'.$ext : '');
    }
}
