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
        if (empty($expoToken) || !str_starts_with($expoToken, 'ExponentPushToken[')) {
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->asJson()
                ->post(self::ENDPOINT, [
                    'to'       => $expoToken,
                    'title'    => $title,
                    'body'     => $body,
                    'data'     => $data,
                    'sound'    => 'default',
                    'priority' => 'high',
                ]);

            if (!$response->successful()) {
                Log::warning('ExpoPush não retornou sucesso', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('ExpoPush falhou', ['message' => $e->getMessage()]);
            return false;
        }
    }
}
