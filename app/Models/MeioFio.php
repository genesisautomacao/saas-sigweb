<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\LogsGeometryChanges;

class MeioFio extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges;

    /** Croqui Antes/Depois na Auditoria (PoC AC 2026-08-23). */
    public function geometryLogLabel(): string
    {
        return 'Geometria do meio-fio alterada';
    }

    /** Auditoria (PoC AC 2026-08-23): loga qualquer campo alterado; geo fica de fora. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['geo', 'created_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'meio_fios';

    protected $fillable = [
        'tenant_id', 'sequential_id', 'code',
        'extensao_geo',
        'logradouro_id', 'observacoes',
        'dados_customizados',
        'geo',
    ];

    protected $casts = [
        'dados_customizados' => 'array', // campos do município (item 75)
    ];

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('meio_fios')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();
        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        // Aceita LineString único e promove para MultiLineString (coluna é MULTILINESTRING)
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('" . json_encode($value) . "'))");
    }

    public function logradouro()
    {
        return $this->belongsTo(Logradouro::class, 'logradouro_id');
    }
}
