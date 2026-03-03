<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;

class UnidadeImobiliaria extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $fillable = ['tenant_id', 'sequential_id', 'lote_id', 'codigo_imovel_tributario', 'inscricao_imobiliaria', 'proprietario_id', 'code', 'geo'];
    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo']))
            return null;
        $result = DB::table('unidade_imobiliarias')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])->first();
        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        // Se a geometria for nula (Ex: apartamento sem ponto no mapa), salva como NULL puro
        if (empty($value)) {
            $this->attributes['geo'] = null;
            return;
        }
        
        $this->attributes['geo'] = DB::raw("ST_GeomFromGeoJSON('" . json_encode($value) . "')");
    }
}