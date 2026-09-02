<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use App\Traits\LogsGeometryChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Linha de desejo do estudo Origem/Destino (docs/piuma.txt §2.7): liga uma
 * origem a uma região de destino com `valores` = volume de deslocamentos.
 * Camada analítica — no mapa, a espessura é proporcional a `valores`.
 */
class MobFluxo extends Model
{
    use BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges, SoftDeletes;

    public const DESTINOS = [
        'centro' => 'Centro',
        'norte' => 'Norte',
        'sul' => 'Sul',
        'leste' => 'Leste',
        'nordeste' => 'Nordeste',
        'sudoeste' => 'Sudoeste',
        'oeste' => 'Oeste',
    ];

    protected $table = 'mob_fluxos';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'destino', 'valores',
        'dados_customizados', 'geo',
    ];

    protected $casts = [
        'dados_customizados' => 'array',
        'valores' => 'integer',
    ];

    protected $hidden = ['geo'];

    protected $appends = ['geo_json'];

    public function geometryLogLabel(): string
    {
        return 'Geometria do fluxo O/D alterada';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['geo', 'created_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getGeoJsonAttribute()
    {
        if (! isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table($this->table)
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();

        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('".json_encode($value)."'))");
    }
}
