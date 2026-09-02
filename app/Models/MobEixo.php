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
 * Eixo de mobilidade (docs/piuma.txt §2.5): ciclovias, eixos comerciais,
 * rotas de transporte de cargas e rodovias (recortadas ao município).
 * `extensao_geo` em METROS (padrão do sistema); exibição formata em km.
 * Extras da rodovia DER-ES (sre, sigla, km...) → dados_customizados.
 */
class MobEixo extends Model
{
    use BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges, SoftDeletes;

    public const TIPOS = [
        'ciclovia' => 'Rede Cicloviária',
        'eixo_comercial' => 'Eixo Comercial',
        'rota_carga' => 'Rota de Transporte de Cargas',
        'rodovia' => 'Rodovia',
    ];

    protected $table = 'mob_eixos';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'tipo', 'name', 'extensao_geo',
        'dados_customizados', 'geo',
    ];

    protected $casts = [
        'dados_customizados' => 'array',
    ];

    protected $hidden = ['geo'];

    protected $appends = ['geo_json'];

    public function geometryLogLabel(): string
    {
        return 'Geometria do eixo de mobilidade alterada';
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
