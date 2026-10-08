<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Chamado aberto pelo cidadão no MAPA PÚBLICO (R80-1, módulo `chamados_mapa`).
 * Sem login: o tenant vem explícito da página pública (não há tenancy do Filament lá).
 * A geometria (POINT) é gravada por SQL com bindings — `definirPonto()`.
 */
class ChamadoMapa extends Model
{
    use BelongsToTenant, HasTenantSequentialId, LogsActivity, SoftDeletes;

    protected $table = 'chamados_mapa';

    public const ASSUNTOS = [
        'solicitacao' => 'Solicitação',
        'sugestao' => 'Sugestão',
        'reclamacao' => 'Reclamação',
    ];

    /** Situações fixas (decisão do usuário 2026-10-08) — rótulo + cor (mapa, badge, e-mail). */
    public const SITUACOES = [
        'pendente' => ['label' => 'Pendente', 'cor' => '#eab308', 'filament' => 'warning'],
        'em_atendimento' => ['label' => 'Em atendimento', 'cor' => '#2563eb', 'filament' => 'info'],
        'atendido' => ['label' => 'Atendido', 'cor' => '#16a34a', 'filament' => 'success'],
        'irrelevante' => ['label' => 'Irrelevante ou Falso', 'cor' => '#dc2626', 'filament' => 'danger'],
    ];

    protected $fillable = [
        'tenant_id', 'sequential_id', 'protocolo',
        'nome', 'celular', 'email', 'cpf',
        'assunto', 'titulo', 'descricao', 'foto', 'foto_disco',
        'situacao', 'resposta', 'observacao_interna', 'situacao_alterada_em', 'atendido_por_id',
        'ip', 'user_agent',
    ];

    protected $hidden = ['geo'];

    protected $casts = [
        'situacao_alterada_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Protocolo = {ano}.{sequencial de 6 dígitos} — o sequencial já vem do
        // HasTenantSequentialId (que considera a lixeira: número nunca se repete).
        static::creating(function (self $chamado) {
            if (blank($chamado->protocolo) && $chamado->sequential_id) {
                $chamado->protocolo = now()->format('Y').'.'.str_pad((string) $chamado->sequential_id, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['geo', 'created_at', 'updated_at', 'ip', 'user_agent'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function atendidoPor()
    {
        return $this->belongsTo(User::class, 'atendido_por_id')->withTrashed();
    }

    public static function rotuloSituacao(?string $s): string
    {
        return self::SITUACOES[$s]['label'] ?? (string) $s;
    }

    public static function corSituacao(?string $s): string
    {
        return self::SITUACOES[$s]['cor'] ?? '#6b7280';
    }

    /** Grava o ponto (lon/lat WGS84) sem interpolar valores no SQL. */
    public function definirPonto(?float $lon, ?float $lat): void
    {
        if ($lon === null || $lat === null) {
            DB::table($this->table)->where('id', $this->id)->update(['geo' => null]);

            return;
        }

        DB::update(
            "UPDATE {$this->table} SET geo = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?",
            [$lon, $lat, $this->id],
        );
    }

    /** ['lat' => .., 'lon' => ..] ou null (1 query — não usar em loop). */
    public function coordenadas(): ?array
    {
        $row = DB::table($this->table)->where('id', $this->id)->whereNotNull('geo')
            ->selectRaw('ST_Y(geo) AS lat, ST_X(geo) AS lon')->first();

        return $row ? ['lat' => (float) $row->lat, 'lon' => (float) $row->lon] : null;
    }

    /** Disco das fotos: bucket PRIVADO "midia" quando configurado; senão o disco local privado. */
    public static function discoFotoPadrao(): string
    {
        return filled(config('filesystems.disks.midia.key')) ? 'midia' : 'local';
    }

    /** URL temporária da foto (só a equipe abre — bucket/disco privados). */
    public function urlFoto(): ?string
    {
        if (blank($this->foto)) {
            return null;
        }

        $disco = $this->foto_disco ?: self::discoFotoPadrao();

        try {
            if ($disco === 'midia') {
                return Storage::disk('midia')->temporaryUrl($this->foto, now()->addMinutes(30));
            }

            return route('chamados-mapa.foto', $this);
        } catch (\Throwable) {
            return null;
        }
    }
}
