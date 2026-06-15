<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;

class Jazigo extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $fillable = ['tenant_id', 'sequential_id', 'quadra_cemiterio_id', 'proprietario_id', 'code', 'codigo', 'tipo', 'status', 'area_geo', 'geo'];
    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'])) return null;
        $result = DB::table('jazigos')->select(DB::raw('ST_AsGeoJSON(geo, 6) as geo_json'))
            ->where('id', $this->attributes['id'])->first();
        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('" . json_encode($value) . "'))");
    }

    public function quadraCemiterio() { return $this->belongsTo(QuadraCemiterio::class, 'quadra_cemiterio_id'); }
    public function proprietario() { return $this->belongsTo(Pessoa::class, 'proprietario_id'); }
    public function documentos() { return $this->morphMany(Documento::class, 'documentable'); }
    public function falecidos() { return $this->hasMany(JazigoFalecido::class); }
}