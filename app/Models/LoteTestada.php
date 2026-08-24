<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\LogsGeometryChanges;

class LoteTestada extends Model
{
    use SoftDeletes, BelongsToTenant, LogsActivity, LogsGeometryChanges;

    /** Croqui Antes/Depois na Auditoria (PoC AC 2026-08-23). */
    public function geometryLogLabel(): string
    {
        return 'Geometria da testada alterada';
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

    protected $table = 'lote_testadas';

    protected $fillable = [
        'tenant_id', 'lote_id', 'logradouro_id', 'secao_logradouro_id',
        'tipo', 'comprimento', 'dados_customizados', 'geo',
    ];

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    protected $casts = [
        'comprimento' => 'decimal:2',
        'dados_customizados' => 'array', // campos do município (item 75)
    ];

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || empty($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('lote_testadas')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();
        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('" . json_encode($value) . "'))");
    }

    public function lote()
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }

    public function logradouro()
    {
        return $this->belongsTo(Logradouro::class, 'logradouro_id');
    }

    public function secaoLogradouro()
    {
        return $this->belongsTo(SecaoLogradouro::class, 'secao_logradouro_id');
    }
}
