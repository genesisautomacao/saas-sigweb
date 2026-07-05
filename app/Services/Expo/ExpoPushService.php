<?php

namespace App\Services\Expo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    /**
     * Envia push notification via Expo Push API.
     * Retorna true em caso de sucesso, false em falha — nunca lança exceção
     * para não interromper o fluxo principal (push é best-effort).
     */
    public function send(string $expoToken, string $title, string $body, array $data = []): bool
    {
        if (empty($expoToken) || ! str_starts_with($expoToken, 'ExponentPushToken[')) {
            // Antes falhava em silêncio — agora deixa rastro (token não salvo / FCM cru).
            Log::warning('ExpoPush: token ausente ou inválido (não começa com "ExponentPushToken[").', [
                'token' => $expoToken !== '' ? substr($expoToken, 0, 24).'…' : '(vazio)',
            ]);

            return false;
        }

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->asJson()
                ->post(self::ENDPOINT, [
                    'to' => $expoToken,
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                    'sound' => 'default',
                    'priority' => 'high',
                ]);

            if (! $response->successful()) {
                Log::warning('ExpoPush: HTTP não-2xx da Expo.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            // A Expo devolve HTTP 200 MESMO quando a entrega falha: o status real vem no "ticket".
            // Mensagem única → {"data": {"status": "ok"|"error", ...}}; às vezes vem como lista.
            $ticket = $response->json('data');
            if (is_array($ticket) && array_is_list($ticket)) {
                $ticket = $ticket[0] ?? [];
            }
            $status = is_array($ticket) ? ($ticket['status'] ?? null) : null;

            if ($status === 'error') {
                // Ex.: DeviceNotRegistered (token velho) | InvalidCredentials/MismatchSenderId (FCM não configurado no EAS).
                Log::warning('ExpoPush: ticket com erro (entrega falhou).', [
                    'error' => $ticket['details']['error'] ?? null,
                    'message' => $ticket['message'] ?? null,
                    'ticket' => $ticket,
                ]);

                return false;
            }

            // Rastro de sucesso (antes não logava nada — impossível confirmar disparo).
            Log::info('ExpoPush: enviado.', [
                'ticket_id' => is_array($ticket) ? ($ticket['id'] ?? null) : null,
                'status' => $status,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('ExpoPush falhou (exceção).', ['message' => $e->getMessage()]);

            return false;
        }
    }
}
