<?php

namespace App\Models;

use App\Services\Expo\ExpoPushService;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MensagemChamado extends Model
{
    use BelongsToTenant;

    protected $table = 'mensagens_chamado';

    protected $fillable = ['tenant_id', 'chamado_id', 'user_id', 'texto', 'publica', 'lido_em'];

    protected $casts = [
        'publica' => 'boolean',
        'lido_em' => 'datetime',
    ];

    public function chamado()
    {
        return $this->belongsTo(Chamado::class, 'chamado_id');
    }

    public function remetente()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Mensagem PÚBLICA (169/171) → push ao cidadão autor do chamado (inclusive após encerrado).
     * Mensagem PRIVADA (170) = interna da prefeitura, não notifica o cidadão.
     * Best-effort: reusa o ExpoPushService (mesmo padrão de Mensagem).
     */
    protected static function booted(): void
    {
        static::created(function (MensagemChamado $m) {
            if (! $m->publica) {
                return;
            }

            $chamado = Chamado::query()->select('id', 'user_id', 'protocolo')->find($m->chamado_id);
            $cidadao = $chamado?->user_id
                ? User::query()->select('id', 'expo_push_token')->find($chamado->user_id)
                : null;

            if (! $cidadao?->expo_push_token) {
                return;
            }

            app(ExpoPushService::class)->send(
                expoToken: $cidadao->expo_push_token,
                title: 'Atualização do seu chamado '.($chamado->protocolo ?? ''),
                body: mb_strimwidth($m->texto ?? '', 0, 100, '...'),
                data: ['tipo' => 'chamado_mensagem', 'chamadoId' => $m->chamado_id],
            );
        });
    }
}
