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
 * Ponto de interesse da mobilidade urbana (docs/piuma.txt §2.3) — absorve os
 * 7 JSONs de POI do levantamento (um toggle por categoria no mapa).
 */
class MobPontoInteresse extends Model
{
    use BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges, SoftDeletes;

    public const CATEGORIAS = [
        'comercio_servicos' => 'Comércio e Serviços',
        'educacao' => 'Educação',
        'saude' => 'Saúde',
        'religioso' => 'Religioso',
        'turismo_lazer_esporte' => 'Turismo, Lazer e Esporte',
        'industria' => 'Indústria',
        'posto_combustivel' => 'Posto de Combustível',
    ];

    protected $table = 'mob_pontos_interesse';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'categoria', 'name', 'numero',
        'dados_customizados', 'geo',
    ];

    protected $casts = [
        'dados_customizados' => 'array',
    ];

    protected $hidden = ['geo'];

    protected $appends = ['geo_json'];

    public function geometryLogLabel(): string
    {
        return 'Posição do ponto de interesse alterada';
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
        // POINT puro (sem ST_Multi)
        $this->attributes['geo'] = DB::raw("ST_GeomFromGeoJSON('".json_encode($value)."')");
    }
}
