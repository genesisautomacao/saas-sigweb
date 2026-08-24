<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use App\Traits\LogsGeometryChanges;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Bairro extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges;

    public function getActivitylogOptions(): LogOptions
    {
        // Auditoria completa (PoC AC 2026-08-23): qualquer campo alterado entra no log.
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['geo', 'created_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** Histórico cartográfico do bairro na Auditoria (B13 / item 044). */
    public function geometryLogLabel(): string
    {
        return 'Geometria do bairro alterada';
    }

    protected $table = 'bairros';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'codigo',
        'code',
        'name',
        'dados_customizados',
        'area_geo',
        'geo',
    ];

    protected $casts = [
        'dados_customizados' => 'array', // campos do município (item 75)
    ];

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    // Leitura idêntica ao original
    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('bairros')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();
        return $result ? json_decode($result->geo_json) : null;
    }

    // Gravação idêntica ao original
    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('" . json_encode($value) . "'))");
    }

    // --- RELACIONAMENTOS ---
    public function quadras()
    {
        return $this->hasMany(Quadra::class);
    }
}