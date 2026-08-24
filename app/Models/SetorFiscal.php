<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\LogsGeometryChanges;

class SetorFiscal extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges;

    /** Croqui Antes/Depois na Auditoria (PoC AC 2026-08-23). */
    public function geometryLogLabel(): string
    {
        return 'Geometria do setor fiscal alterada';
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

    protected $table = 'setores_fiscais';

    protected $fillable = ['tenant_id', 'sequential_id', 'codigo', 'pgv_parametro_id', 'nome', 'dados_customizados', 'geo', 'area_geo'];

    protected $casts = [
        'dados_customizados' => 'array', // campos do município (item 75)
    ];
    
    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    // Mutators Espaciais Idênticos ao do Lote
    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo']))
            return null;
        
        $result = DB::table('setores_fiscais')
            ->select(DB::raw('ST_AsGeoJSON(geo, 6) as geo_json'))
            ->where('id', $this->attributes['id'])->first();
            
        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        if ($value) {
            $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('" . json_encode($value) . "'))");
        } else {
            $this->attributes['geo'] = null;
        }
    }

    public function parametro() {
        return $this->belongsTo(PgvParametro::class, 'pgv_parametro_id');
    }
}