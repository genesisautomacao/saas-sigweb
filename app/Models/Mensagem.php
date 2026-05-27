<?php

namespace App\Models;

use App\Services\Expo\ExpoPushService;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Mensagem extends Model
{
    use BelongsToTenant;

    protected $table = 'mensagens';

    protected $fillable = [
        'tenant_id',
        'remetente_id',
        'destinatario_id',
        'texto',
        'lido_em',
    ];

    protected $casts = [
        'lido_em' => 'datetime',
    ];

    public function remetente()
    {
        return $this->belongsTo(User::class, 'remetente_id');
    }

    public function destinatario()
    {
        return $this->belongsTo(User::class, 'destinatario_id');
    }

    /**
     * Dispara push notification ao destinatário sempre que uma mensagem é criada,
     * independentemente de origem (API mobile, página Filament, tinker, etc.).
     * Best-effort: falhas são logadas pelo service e não interrompem o fluxo.
     */
    protected static function booted(): void
    {
        static::created(function (Mensagem $mensagem) {
            $destinatario = User::query()
                ->select('id', 'expo_push_token')
                ->find($mensagem->destinatario_id);

            if (!$destinatario?->expo_push_token) {
                return;
            }

            $remetente = User::query()
                ->select('id', 'name')
                ->find($mensagem->remetente_id);

            app(ExpoPushService::class)->send(
                expoToken: $destinatario->expo_push_token,
                title: $remetente?->name ?? 'Nova mensagem',
                body: mb_strimwidth($mensagem->texto ?? '', 0, 100, '...'),
                data: [
                    'tipo'       => 'mensagem',
                    'contatoId'  => $mensagem->remetente_id,
                    'mensagemId' => $mensagem->id,
                ],
            );
        });
    }
}
