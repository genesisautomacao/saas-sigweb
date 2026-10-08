<?php

namespace App\Services\ChamadosMapa;

use App\Models\ChamadoMapa;
use App\Notifications\ChamadoMapaSituacaoNotification;
use App\Services\Fiscal\ProprietarioService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * R80-1 — Chamados pelo mapa público: regras de negócio fora da página Livewire.
 *
 *  - registrar(): grava o chamado do cidadão (tenant explícito — o painel público não
 *    tem tenancy do Filament) e o ponto opcional;
 *  - alterarSituacao(): muda a situação e avisa o cidadão por e-mail (Resend, marca do
 *    município), best-effort — falha de e-mail nunca impede o atendimento;
 *  - turnstile*(): "não sou robô" da Cloudflare, ligado só se existir o ApiSetting
 *    "Cloudflare Turnstile" (TURNSTILE_SITE_KEY + TURNSTILE_SECRET_KEY).
 */
class ChamadoMapaService
{
    /** Envios por IP por hora (decisão do usuário 2026-10-08). */
    public const LIMITE_POR_HORA = 10;

    /** Segundos mínimos entre abrir o formulário e enviar (robô envia na hora). */
    public const TEMPO_MINIMO = 3;

    public function registrar(int $tenantId, array $dados, ?float $lon, ?float $lat, ?string $ip, ?string $userAgent): ChamadoMapa
    {
        return DB::transaction(function () use ($tenantId, $dados, $lon, $lat, $ip, $userAgent) {
            $chamado = ChamadoMapa::create([
                'tenant_id' => $tenantId,
                'nome' => trim($dados['nome']),
                'celular' => self::limpar($dados['celular'] ?? null),
                'email' => self::limpar(isset($dados['email']) ? mb_strtolower($dados['email']) : null),
                'cpf' => ProprietarioService::cpfValido($dados['cpf'] ?? null),
                'assunto' => $dados['assunto'],
                'titulo' => trim($dados['titulo']),
                'descricao' => trim($dados['descricao']),
                'foto' => $dados['foto'] ?? null,
                'foto_disco' => filled($dados['foto'] ?? null) ? ($dados['foto_disco'] ?? ChamadoMapa::discoFotoPadrao()) : null,
                'situacao' => 'pendente',
                'ip' => $ip,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
            ]);

            if ($lon !== null && $lat !== null) {
                $chamado->definirPonto($lon, $lat);
            }

            return $chamado;
        });
    }

    /** @return bool true se o e-mail ao cidadão foi enviado */
    public function alterarSituacao(ChamadoMapa $chamado, string $situacao, ?string $resposta, ?string $observacaoInterna, bool $avisarCidadao, ?int $userId): bool
    {
        $mudou = $chamado->situacao !== $situacao;

        $chamado->fill([
            'situacao' => $situacao,
            'resposta' => self::limpar($resposta),
            'observacao_interna' => self::limpar($observacaoInterna),
            'atendido_por_id' => $userId,
        ]);
        if ($mudou) {
            $chamado->situacao_alterada_em = now();
        }
        $chamado->save();

        return $avisarCidadao && filled($chamado->email) ? $this->notificar($chamado) : false;
    }

    /** E-mail ao cidadão com a situação atual — nunca lança. */
    public function notificar(ChamadoMapa $chamado): bool
    {
        try {
            Notification::route('mail', $chamado->email)->notify(new ChamadoMapaSituacaoNotification($chamado->id));

            return true;
        } catch (\Throwable $e) {
            Log::warning("[ChamadoMapa] e-mail do protocolo {$chamado->protocolo} falhou: ".$e->getMessage());

            return false;
        }
    }

    // ── Turnstile (Cloudflare) ─────────────────────────────────────────────────

    public static function turnstileSiteKey(): ?string
    {
        return self::turnstile('TURNSTILE_SITE_KEY');
    }

    public static function turnstileAtivo(): bool
    {
        return filled(self::turnstileSiteKey()) && filled(self::turnstile('TURNSTILE_SECRET_KEY'));
    }

    public static function turnstileValido(?string $token, ?string $ip): bool
    {
        if (! self::turnstileAtivo()) {
            return true;
        }
        if (blank($token)) {
            return false;
        }

        try {
            return (bool) Http::asForm()->timeout(8)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => self::turnstile('TURNSTILE_SECRET_KEY'),
                    'response' => $token,
                    'remoteip' => $ip,
                ])->json('success');
        } catch (\Throwable $e) {
            // Cloudflare fora do ar não pode calar o cidadão: as outras proteções seguem valendo.
            Log::warning('[ChamadoMapa] Turnstile indisponível, aceitando envio: '.$e->getMessage());

            return true;
        }
    }

    private static function turnstile(string $chave): ?string
    {
        try {
            $linha = \Illuminate\Support\Facades\Cache::rememberForever('api_settings.all', fn () => \App\Models\ApiSetting::query()->get()->keyBy('name'))
                ->get('Cloudflare Turnstile');

            $valor = $linha?->data[$chave] ?? null;

            return filled($valor) ? trim($valor) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function limpar(?string $valor): ?string
    {
        $valor = $valor !== null ? trim($valor) : null;

        return $valor === '' ? null : $valor;
    }
}
