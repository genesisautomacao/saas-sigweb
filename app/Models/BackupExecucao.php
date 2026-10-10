<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * INF-3 — uma execução do backup diário dos bancos de produção (script do servidor,
 * `/root/backup-diario.sh`, que grava aqui via `php artisan backup:registrar`).
 *
 * Global, sem tenant. Exibido no /admin (BackupExecucaoResource, só Master).
 */
class BackupExecucao extends Model
{
    protected $table = 'backup_execucoes';

    protected $fillable = [
        'sistema', 'status', 'arquivo', 'tamanho_bytes', 'duracao_segundos', 'erro', 'servidor',
    ];

    protected $casts = [
        'tamanho_bytes' => 'integer',
        'duracao_segundos' => 'integer',
    ];

    public const SISTEMAS = [
        'webgis' => 'SIGWEB (PostgreSQL)',
        'ferramenta' => 'Criador de Sites (MySQL)',
    ];

    public const STATUS = ['sucesso' => 'Sucesso', 'falha' => 'Falha'];

    /** Sem backup bem-sucedido há mais que isso = atrasado (o agendado roda 1×/dia às 3h). */
    public const HORAS_ATRASO = 26;

    public static function ultimoSucesso(string $sistema): ?Carbon
    {
        $quando = static::where('sistema', $sistema)->where('status', 'sucesso')->max('created_at');

        return $quando ? Carbon::parse($quando) : null;
    }

    /** Sistemas sem backup bem-sucedido nas últimas HORAS_ATRASO horas (inclui "nunca"). */
    public static function sistemasAtrasados(): array
    {
        return array_values(array_filter(
            array_keys(self::SISTEMAS),
            fn ($s) => ($u = self::ultimoSucesso($s)) === null || $u->lt(now()->subHours(self::HORAS_ATRASO))
        ));
    }

    public static function formatarTamanho(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1, ',', '.').' MB'
            : number_format($bytes / 1024, 0, ',', '.').' KB';
    }
}
