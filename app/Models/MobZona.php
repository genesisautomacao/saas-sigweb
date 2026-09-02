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
 * Zona de estudo da mobilidade (docs/piuma.txt §2.6): zonas O/D (com % de
 * origens/destinos), quadrantes de estudo, polo industrial e setores
 * censitários IBGE (codigo). area_geo recalculada via PostGIS (decisão 6.6).
 */
class MobZona extends Model
{
    use BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges, SoftDeletes;

    public const TIPOS = [
        'zona_od' => 'Zona Origem/Destino',
        'quadrante' => 'Quadrante de Estudo',
        'polo_industrial' => 'Polo Industrial',
        'setor_censitario' => 'Setor Censitário (IBGE)',
    ];

    protected $table = 'mob_zonas';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'tipo', 'name', 'codigo', 'situacao',
        'origens', 'destinos', 'area_geo',
        'dados_customizados', 'geo',
    ];

    protected $casts = [
        'dados_customizados' => 'array',
        'origens' => 'float',
        'destinos' => 'float',
    ];

    protected $hidden = ['geo'];

    protected $appends = ['geo_json'];

    public function geometryLogLabel(): string
    {
        return 'Geometria da zona de mobilidade alterada';
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
