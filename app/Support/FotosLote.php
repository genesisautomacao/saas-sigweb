<?php

namespace App\Support;

use App\Models\Lote;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fotos dos lotes (frontal + laterais) — R79-1, 2026-10-08.
 *
 * Onde está cada foto é decidido PELO CAMINHO GRAVADO, sem consultar o bucket:
 *  - `{tenant_slug}/lotes_fotos/x.jpg` → bucket público `sigweb-fotos` (disk "fotos"),
 *    servido em https://fotos.sigwebmidia.com.br/{caminho};
 *  - `lotes_fotos/x.jpg` (legado) → disk "public" da VPS (/storage/{caminho}).
 *
 * Foto nova vai para o bucket quando o ApiSetting "Cloudflare R2 Fotos" existe; sem
 * ele (ou se o envio falhar), cai no disk "public" como sempre foi. A leitura dupla
 * fica para sempre: o comando `fotos:migrar-bucket` move o legado, mas uma foto que
 * chegue no meio da migração ou falhe na cópia continua legível.
 *
 * PDFs/ZIPs leem os BYTES pelo disk (dataUri/conteudo), nunca baixam pela URL.
 */
final class FotosLote
{
    public const COLUNAS = ['foto_frontal', 'foto_lateral_esq', 'foto_lateral_dir'];

    public const DISCO_BUCKET = 'fotos';

    public const DISCO_LOCAL = 'public';

    public const PASTA = 'lotes_fotos';

    /** Credenciais do bucket carregadas neste ambiente? */
    public static function bucketAtivo(): bool
    {
        return filled(config('filesystems.disks.fotos.key'))
            && filled(config('filesystems.disks.fotos.endpoint'));
    }

    /** O caminho aponta para o bucket? (`{slug}/lotes_fotos/...`) */
    public static function noBucket(?string $caminho): bool
    {
        return filled($caminho) && preg_match('#^[a-z0-9][a-z0-9-]*/'.self::PASTA.'/#', ltrim($caminho, '/')) === 1;
    }

    public static function disco(?string $caminho): Filesystem
    {
        return Storage::disk(self::noBucket($caminho) ? self::DISCO_BUCKET : self::DISCO_LOCAL);
    }

    /** URL para exibir (img src / link). Bucket = URL pública; legado = /storage. */
    public static function url(?string $caminho): ?string
    {
        if (blank($caminho)) {
            return null;
        }

        $caminho = ltrim($caminho, '/');

        if (self::noBucket($caminho)) {
            return rtrim((string) config('filesystems.disks.fotos.url'), '/').'/'.$caminho;
        }

        return asset('storage/'.$caminho);
    }

    /** Bytes da foto (null se não existir / bucket inacessível). */
    public static function conteudo(?string $caminho): ?string
    {
        if (blank($caminho)) {
            return null;
        }

        try {
            $conteudo = self::disco($caminho)->get(ltrim($caminho, '/'));

            return $conteudo === null || $conteudo === false || $conteudo === '' ? null : $conteudo;
        } catch (\Throwable $e) {
            Log::warning("[FotosLote] falha ao ler {$caminho}: ".$e->getMessage());

            return null;
        }
    }

    /** `data:image/...;base64,...` para DomPDF (BIC, Viabilidade, Produtividade). */
    public static function dataUri(?string $caminho): ?string
    {
        $conteudo = self::conteudo($caminho);
        if ($conteudo === null) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($conteudo) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($conteudo);
    }

    /** Slug da prefeitura para o prefixo do bucket (tenant explícito > tenant do painel). */
    public static function slug(Tenant|int|null $tenant = null): ?string
    {
        if ($tenant instanceof Tenant) {
            return $tenant->slug;
        }

        if (is_int($tenant)) {
            return Tenant::query()->whereKey($tenant)->value('slug');
        }

        return Filament::getTenant()?->slug;
    }

    /** Pasta de destino de uma foto NOVA. */
    public static function diretorio(?string $slug): string
    {
        return (self::bucketAtivo() && filled($slug)) ? $slug.'/'.self::PASTA : self::PASTA;
    }

    /**
     * Grava uma foto nova (push do app) e devolve o caminho a guardar na coluna.
     * Bucket indisponível ou falhando ⇒ disk "public" (a coleta nunca se perde).
     */
    public static function salvar(string $conteudo, string $extensao, ?string $slug): string
    {
        $nome = Str::uuid().'.'.strtolower(preg_replace('/[^a-z0-9]/i', '', $extensao) ?: 'jpg');

        if (self::bucketAtivo() && filled($slug)) {
            $caminho = $slug.'/'.self::PASTA.'/'.$nome;
            try {
                if (Storage::disk(self::DISCO_BUCKET)->put($caminho, $conteudo)) {
                    return $caminho;
                }
            } catch (\Throwable $e) {
                Log::warning("[FotosLote] bucket recusou {$caminho}, gravando local: ".$e->getMessage());
            }
        }

        $caminho = self::PASTA.'/'.$nome;
        Storage::disk(self::DISCO_LOCAL)->put($caminho, $conteudo);

        return $caminho;
    }

    /**
     * Configura um FileUpload de foto de lote (LoteResource e modal do mapa):
     *  - upload novo vai para o bucket (ou para o local, sem credenciais);
     *  - caminho já gravado NUNCA é descartado na hidratação (o Filament filtra o
     *    que não existe no disk do campo — um caminho legado num campo do bucket
     *    sumiria e o save apagaria a foto); a prévia usa a URL certa de cada caminho.
     */
    public static function campoUpload(FileUpload $campo, ?string $slug = null): FileUpload
    {
        $slug ??= self::slug();
        $usaBucket = self::bucketAtivo() && filled($slug);

        return $campo
            ->disk($usaBucket ? self::DISCO_BUCKET : self::DISCO_LOCAL)
            ->directory(self::diretorio($slug))
            ->visibility('public')
            ->fetchFileInformation(false)
            ->getUploadedFileUsing(fn (string $file) => [
                'name' => basename($file),
                'size' => 0,
                'type' => null,
                'url' => self::url($file),
            ]);
    }

    /**
     * Copia as fotos legadas de UM lote para o bucket e troca o caminho na coluna —
     * só depois de conferir que o arquivo chegou. Não apaga o arquivo local (backup;
     * a limpeza é o `fotos:limpar-local`). Grava via DB::table: mover arquivo não é
     * alteração do cadastro (não polui a Auditoria).
     *
     * @return array<string, string> coluna => 'migrada'|'ausente'|'falhou: ...'
     */
    public static function migrarLote(object $lote, string $slug): array
    {
        $resultado = [];
        $novos = [];
        $bucket = Storage::disk(self::DISCO_BUCKET);

        foreach (self::COLUNAS as $coluna) {
            $caminho = $lote->{$coluna} ?? null;
            if (blank($caminho) || self::noBucket($caminho)) {
                continue;
            }

            $conteudo = self::conteudo($caminho);
            if ($conteudo === null) {
                $resultado[$coluna] = 'ausente';

                continue;
            }

            $destino = $slug.'/'.self::PASTA.'/'.basename($caminho);
            try {
                $bucket->put($destino, $conteudo);
                if ($bucket->size($destino) !== strlen($conteudo)) {
                    throw new \RuntimeException('tamanho diferente após o envio');
                }
                $novos[$coluna] = $destino;
                $resultado[$coluna] = 'migrada';
            } catch (\Throwable $e) {
                $resultado[$coluna] = 'falhou: '.$e->getMessage();
            }
        }

        if ($novos !== []) {
            DB::table('lotes')->where('id', $lote->id)->update($novos);
        }

        return $resultado;
    }

    /** Lote com alguma foto ainda no disco local? */
    public static function temFotoLocal(object $lote): bool
    {
        foreach (self::COLUNAS as $coluna) {
            if (filled($lote->{$coluna} ?? null) && ! self::noBucket($lote->{$coluna})) {
                return true;
            }
        }

        return false;
    }
}
