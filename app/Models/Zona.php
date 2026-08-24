<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\LogsGeometryChanges;
use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zona extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges;

    /** Croqui Antes/Depois na Auditoria (PoC AC 2026-08-23). */
    public function geometryLogLabel(): string
    {
        return 'Geometria da zona alterada';
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

    protected $table = 'zonas';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'codigo',
        'dados_customizados',
        'perimetro_id',
        'code',
        'sigla',
        'name',
        'rgb',
        'area_geo',
        'geo',
    ];

    protected $casts = [
        'dados_customizados' => 'array', // campos do município (item 75)
    ];

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    // --- GEOMETRIA ---
    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('zonas')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();
        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('" . json_encode($value) . "'))");
    }

    // --- RELACIONAMENTOS ---
    public function perimetro()
    {
        return $this->belongsTo(PerimetroUrbano::class, 'perimetro_id');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }
}
