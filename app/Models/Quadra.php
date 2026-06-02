<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Quadra extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $table = 'quadras';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'perimetro_id',
        'bairro_id',
        'loteamento_id',
        'code',
        'name',
        'setor_codigo',
        'area_geo',
        'geo',
    ];

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    // --- GEOMETRIA ---
    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('quadras')
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

    public function bairro()
    {
        return $this->belongsTo(Bairro::class, 'bairro_id');
    }

    public function loteamento()
    {
        return $this->belongsTo(Loteamento::class, 'loteamento_id');
    }
}