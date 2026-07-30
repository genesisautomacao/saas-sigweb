<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Edificacao extends Model
{
    use BelongsToTenant, HasTenantSequentialId, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tipo', 'tp_construcao', 'caracteristica_construcao', 'estado_conservacao', 'area_geo', 'dados_customizados'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'edificacoes';

    protected $fillable = ['tenant_id', 'sequential_id', 'lote_id', 'code', 'tipo', 'tp_construcao', 'caracteristica_construcao', 'estado_conservacao', 'pavimento', 'area_geo', 'dados_customizados', 'geo'];

    protected $casts = [
        'dados_customizados' => 'array', // R67-1 — campos customizados do município
    ];

    protected $hidden = ['geo'];

    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute()
    {
        if (! isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('edificacoes')
            ->select(DB::raw('ST_AsGeoJSON(geo, 6) as geo_json'))
            ->where('id', $this->attributes['id'])->first();

        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('".json_encode($value)."'))");
    }
}
