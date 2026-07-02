<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class FaceQuadra extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'face_quadras';

    protected $fillable = [
        'tenant_id', 'sequential_id', 'code', 'name',
        'quadra_id', 'zona_id', 'logradouro_id', 'extensao_geo',
        'valor_m2_calculado', 'distancia_polo', 'pgv_polo_id', 'setor_fiscal_id',
        'geo',
    ];

    protected $casts = [
        'extensao_geo'       => 'decimal:2',
        'valor_m2_calculado' => 'decimal:2',
        'distancia_polo'     => 'decimal:2',
    ];

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'] ?? null)) {
            return null;
        }
        $r = DB::table('face_quadras')->select(DB::raw('ST_AsGeoJSON(geo, 6) as g'))->where('id', $this->attributes['id'])->first();
        return $r && $r->g ? json_decode($r->g) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = empty($value) ? null : DB::raw("ST_Multi(ST_GeomFromGeoJSON('" . json_encode($value) . "'))");
    }

    public function quadra()
    {
        return $this->belongsTo(Quadra::class);
    }

    public function zona()
    {
        return $this->belongsTo(Zona::class);
    }

    public function logradouro()
    {
        return $this->belongsTo(Logradouro::class);
    }

    public function polo()
    {
        return $this->belongsTo(PgvPolo::class, 'pgv_polo_id');
    }

    public function setorFiscal()
    {
        return $this->belongsTo(SetorFiscal::class);
    }
}
