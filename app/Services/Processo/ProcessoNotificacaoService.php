<?php

namespace App\Services\Processo;

use App\Models\ProcessoDigital;
use App\Notifications\ProcessoDigitalNotification;
use Illuminate\Support\Facades\Log;

/**
 * Dispara os e-mails automáticos ao cidadão (requerente) a cada transição do processo digital.
 *
 * É best-effort no espírito do ExpoPushService: NUNCA lança exceção — uma falha de e-mail
 * jamais pode quebrar a tramitação do processo. A decisão de QUAL e-mail enviar é centralizada
 * aqui, a partir do `status` resultante do processo + o `status_parecer` da tramitação:
 *
 *  status resultante        | status_parecer | e-mail
 *  -------------------------|----------------|---------------------------
 *  concluido                | (qualquer)     | concluido
 *  pendente_correcao        | reprovado      | correcao (inclui o parecer)
 *  aguardando_solicitante   | encaminhado/aprovado | acao_necessaria
 *  em_andamento             | aprovado       | aprovado (avançou p/ outro analista)
 *  em_andamento             | encaminhado/reprovado | — (nenhum: não é do cidadão)
 */
class ProcessoNotificacaoService
{
    public static function notificarTransicao(ProcessoDigital $processo, string $statusParecer, ?string $parecer = null): void
    {
        try {
            $evento = self::resolverEvento($processo->status, $statusParecer);

            if ($evento === null) {
                return; // Transição que não interessa ao cidadão (ex.: bounce entre analistas).
            }

            $requerente = $processo->requerente;

            // Só notifica o cidadão dono do processo, e só se houver e-mail.
            if (!$requerente || !$requerente->isCidadao() || empty($requerente->email)) {
                return;
            }

            $requerente->notify(new ProcessoDigitalNotification($processo->id, $evento, $parecer));
        } catch (\Throwable $e) {
            Log::warning('Falha ao notificar cidadão sobre processo digital', [
                'processo_id' => $processo->id ?? null,
                'status_parecer' => $statusParecer,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    /** Mapeia (status resultante, status_parecer) → tipo de e-mail (ou null p/ "não enviar"). */
    private static function resolverEvento(?string $status, string $statusParecer): ?string
    {
        return match (true) {
            $status === 'concluido' => 'concluido',
            $status === 'pendente_correcao' => 'correcao',
            $status === 'aguardando_solicitante' => 'acao_necessaria',
            $status === 'em_andamento' && $statusParecer === 'aprovado' => 'aprovado',
            default => null,
        };
    }
}
