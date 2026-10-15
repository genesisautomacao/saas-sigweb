<?php

namespace App\Services\ArquivosGerados;

use App\Models\ArquivoGerado;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Midia;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * R84-4 — ciclo de vida dos arquivos gerados pela fila: registra o pedido, guarda o
 * arquivo pronto (bucket PRIVADO `midia`, com CPF/proprietário dentro; bucket recusou
 * ⇒ disco local privado da VPS), avisa no sininho e apaga o que venceu.
 *
 * O download passa SEMPRE pela rota `arquivos-gerados.baixar` (confere quem pede e
 * assina um link novo a cada clique) — o aviso do sininho fica válido até expirar.
 */
class ArquivosGeradosService
{
    public const PASTA = 'arquivos-gerados';

    public static function registrar(string $tipo, string $titulo, array $parametros, User $user, ?Tenant $tenant, string $painel = 'app'): ArquivoGerado
    {
        return ArquivoGerado::create([
            'tenant_id' => $tenant?->id,
            'user_id' => $user->id,
            'painel' => $painel,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'parametros' => $parametros,
            'status' => 'na_fila',
        ]);
    }

    /** Move o arquivo local pronto para o armazenamento definitivo e marca como pronto. */
    public static function guardar(ArquivoGerado $arquivo, string $caminhoLocal, string $nomeArquivo, ?string $mensagem = null): void
    {
        $slug = $arquivo->tenant?->slug ?? 'sigweb';
        $relativo = now()->format('Y-m').'/'.Str::ulid().'/'.$nomeArquivo;
        $disco = 'local';
        $caminho = self::PASTA.'/'.$relativo;

        if (Midia::bucketAtivo('midia')) {
            $destino = $slug.'/'.self::PASTA.'/'.$relativo;
            try {
                $stream = fopen($caminhoLocal, 'rb');
                $ok = Storage::disk('midia')->writeStream($destino, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if ($ok) {
                    [$disco, $caminho] = ['midia', $destino];
                } else {
                    Log::warning("[ArquivosGerados] bucket recusou {$destino}; gravado na VPS.");
                }
            } catch (\Throwable $e) {
                Log::warning("[ArquivosGerados] bucket recusou {$destino}; gravado na VPS: ".$e->getMessage());
            }
        }

        if ($disco === 'local') {
            $stream = fopen($caminhoLocal, 'rb');
            Storage::disk('local')->writeStream($caminho, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $arquivo->forceFill([
            'status' => 'pronto',
            'progresso' => 100,
            'disco' => $disco,
            'caminho' => $caminho,
            'nome_arquivo' => $nomeArquivo,
            'tamanho' => filesize($caminhoLocal) ?: null,
            'mensagem' => $mensagem,
            'concluido_em' => now(),
            'expira_em' => now()->addDays(ArquivoGerado::DIAS_VALIDADE),
        ])->save();
    }

    public static function urlDownload(ArquivoGerado $arquivo): string
    {
        return route('arquivos-gerados.baixar', $arquivo);
    }

    /** Resposta do download (já autorizado pela rota): link temporário do bucket ou stream local. */
    public static function respostaDownload(ArquivoGerado $arquivo)
    {
        $nome = $arquivo->nome_arquivo ?: basename((string) $arquivo->caminho);

        if ($arquivo->disco === 'midia') {
            return redirect()->away(Storage::disk('midia')->temporaryUrl($arquivo->caminho, now()->addMinutes(10), [
                'ResponseContentDisposition' => 'attachment; filename="'.addslashes($nome).'"',
            ]));
        }

        return Storage::disk('local')->download($arquivo->caminho, $nome);
    }

    public static function notificarPronto(ArquivoGerado $arquivo): void
    {
        if (! $arquivo->user) {
            return;
        }

        $aviso = Notification::make()
            ->title($arquivo->titulo.' — pronto')
            ->body(trim(($arquivo->mensagem ? $arquivo->mensagem.' · ' : '').'Disponível por '.ArquivoGerado::DIAS_VALIDADE.' dias em "Arquivos Gerados".'))
            ->success()
            ->icon('heroicon-o-arrow-down-tray')
            ->actions([
                Action::make('baixar')->label('Baixar')->button()->url(self::urlDownload($arquivo), shouldOpenInNewTab: true),
            ])
            ->toDatabase();

        // DatabaseNotification do Filament é ShouldQueue: quem chama já é o job da fila,
        // então grava na hora (sem worker ficaria preso na tabela jobs).
        $arquivo->user->notifyNow($aviso);
    }

    public static function notificarErro(ArquivoGerado $arquivo): void
    {
        if (! $arquivo->user) {
            return;
        }

        $aviso = Notification::make()
            ->title($arquivo->titulo.' — falhou')
            ->body($arquivo->mensagem ?: 'Erro inesperado. Tente de novo ou avise o suporte.')
            ->danger()
            ->toDatabase();

        $arquivo->user->notifyNow($aviso);
    }

    /** Apaga arquivos vencidos (arquivo + linha) e destrava pedidos parados há mais de 6 h. */
    public static function limparExpirados(): array
    {
        $apagados = 0;

        ArquivoGerado::query()
            ->whereNotNull('expira_em')->where('expira_em', '<', now())
            ->chunkById(200, function ($lote) use (&$apagados) {
                foreach ($lote as $arquivo) {
                    self::apagarArquivo($arquivo);
                    $arquivo->delete();
                    $apagados++;
                }
            });

        $travados = ArquivoGerado::query()
            ->whereIn('status', ['na_fila', 'processando'])
            ->where('updated_at', '<', now()->subHours(6))
            ->update([
                'status' => 'erro',
                'mensagem' => 'O processamento não terminou (fila parada ou servidor reiniciado). Gere de novo.',
                'expira_em' => now()->addDays(ArquivoGerado::DIAS_VALIDADE),
            ]);

        return ['apagados' => $apagados, 'travados' => $travados];
    }

    public static function apagarArquivo(ArquivoGerado $arquivo): void
    {
        if (blank($arquivo->caminho)) {
            return;
        }
        try {
            Storage::disk($arquivo->disco === 'midia' ? 'midia' : 'local')->delete($arquivo->caminho);
        } catch (\Throwable $e) {
            Log::warning("[ArquivosGerados] falha ao apagar {$arquivo->caminho}: ".$e->getMessage());
        }
    }
}
