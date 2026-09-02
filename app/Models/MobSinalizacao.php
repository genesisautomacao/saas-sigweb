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
 * Placa/marcação de sinalização viária (Mobilidade Urbana — docs/piuma.txt §2.2).
 * POINT que aponta para o catálogo MobTipoSinalizacao (cor/ícone/tipo vêm de lá);
 * descricao_original preserva o texto cru do levantamento de campo.
 */
class MobSinalizacao extends Model
{
    use BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges, SoftDeletes;

    protected $table = 'mob_sinalizacoes';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'tipo_sinalizacao_id', 'descricao_original', 'observacao',
        'dados_customizados', 'geo',
    ];

    protected $casts = [
        'dados_customizados' => 'array',
    ];

    protected $hidden = ['geo'];

    protected $appends = ['geo_json'];

    public function geometryLogLabel(): string
    {
        return 'Posição da sinalização alterada';
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

    public function tipoSinalizacao()
    {
        return $this->belongsTo(MobTipoSinalizacao::class, 'tipo_sinalizacao_id');
    }
}
