<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Diagnóstico de push (chamados). Envia um push de teste via Expo e imprime o
 * ticket e o receipt — para descobrir se o problema é: token não salvo, gatilho
 * não disparado, ou credencial FCM (que a Expo devolve dentro de um HTTP 200).
 */
class TestPushChamado extends Command
{
    protected $signature = 'chamado:test-push {user_id? : ID do usuário (padrão: 1º cidadão com token)}';

    protected $description = 'Envia um push de teste via Expo e imprime o ticket/receipt (diagnóstico de notificação de chamados)';

    public function handle(): int
    {
        $user = $this->argument('user_id')
            ? User::find($this->argument('user_id'))
            : User::whereNotNull('expo_push_token')->where('tipo', 'cidadao')->first();

        if (! $user) {
            $this->error('Usuário não encontrado (ou nenhum cidadão com expo_push_token salvo).');

            return self::FAILURE;
        }

        $this->line("Usuário: #{$user->id}  {$user->email}");
        $token = $user->expo_push_token;

        if (empty($token)) {
            $this->error('Este usuário NÃO tem expo_push_token salvo → problema = TOKEN NÃO SALVO (o app não enviou no login/register).');

            return self::FAILURE;
        }
        $this->line("Token: {$token}");

        if (! str_starts_with($token, 'ExponentPushToken[')) {
            $this->warn('⚠️ O token não começa com "ExponentPushToken[" — é inválido/FCM cru. O ExpoPushService o rejeita.');
        }

        // 1) Envia o push
        $resp = Http::acceptJson()->asJson()->post('https://exp.host/--/api/v2/push/send', [
            'to' => $token,
            'title' => 'Teste de push (SIGWEB)',
            'body' => 'Se você recebeu isto, o push está funcionando.',
            'sound' => 'default',
            'priority' => 'high',
            'data' => ['tipo' => 'teste', 'chamado_id' => 0],
        ]);

        $this->newLine();
        $this->line('=== RESPOSTA DO SEND (ticket) ===');
        $this->line('HTTP: '.$resp->status());
        $this->line($resp->body());

        $ticket = $resp->json('data');
        if (is_array($ticket) && array_is_list($ticket)) {
            $ticket = $ticket[0] ?? [];
        }
        $status = is_array($ticket) ? ($ticket['status'] ?? null) : null;

        if ($status === 'error') {
            $err = $ticket['details']['error'] ?? '(sem details.error)';
            $this->error("Ticket com ERRO: {$err} — ".($ticket['message'] ?? ''));
            $this->line($this->explicar($err));

            return self::FAILURE;
        }

        $ticketId = is_array($ticket) ? ($ticket['id'] ?? null) : null;
        if (! $ticketId) {
            $this->warn('Sem ticket id — não dá para consultar o receipt.');

            return self::SUCCESS;
        }

        // 2) Consulta o receipt (erros de entrega FCM/APNs costumam aparecer aqui)
        $this->info("Ticket OK (id={$ticketId}). Consultando receipt em ~3s...");
        sleep(3);
        $rec = Http::acceptJson()->asJson()->post('https://exp.host/--/api/v2/push/getReceipts', [
            'ids' => [$ticketId],
        ]);
        $this->newLine();
        $this->line('=== RECEIPT ===');
        $this->line($rec->body());

        $receipt = $rec->json("data.{$ticketId}");
        if (is_array($receipt) && ($receipt['status'] ?? null) === 'error') {
            $err = $receipt['details']['error'] ?? '(sem details.error)';
            $this->error("Receipt com ERRO: {$err}");
            $this->line($this->explicar($err));
        } elseif (is_array($receipt) && ($receipt['status'] ?? null) === 'ok') {
            $this->info('Receipt OK — a Expo entregou ao FCM/APNs. Se não chegou no aparelho, é config do device/app.');
        } else {
            $this->comment('Receipt ainda não disponível (a Expo pode levar alguns minutos). Rode de novo para reconsultar.');
        }

        return self::SUCCESS;
    }

    private function explicar(string $err): string
    {
        return match ($err) {
            'DeviceNotRegistered' => '→ Token velho/invalidado (build trocada, app reinstalado). O app precisa re-registrar o expo_push_token.',
            'MismatchSenderId', 'InvalidCredentials' => '→ Credencial FCM V1 não configurada/errada no EAS (app-side). Suspeito nº 1.',
            'MessageTooBig' => '→ Payload grande demais.',
            'MessageRateExceeded' => '→ Rate limit; enviar mais devagar.',
            default => '→ Consulte a lista de push errors da Expo.',
        };
    }
}
