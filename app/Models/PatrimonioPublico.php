<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PatrimonioPublico extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'patrimonio_publicos';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'code',
        'name',
        'address',
        'description',
        'tipo_patrimonio_id',
        'geo'
    ];

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->code)) {
                $model->code = (string) Str::uuid();
            }
        });
    }

    /**
     * Extrai a geometria do PostGIS para o front-end (Mapa)
     */
    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('patrimonio_publicos')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();
            
        return $result ? json_decode($result->geo_json) : null;
    }

    /**
     * Salva a geometria recebida no PostGIS
     * Como a coluna é GEOMETRY genérica, não forçamos ST_Multi.
     */
    public function setGeoAttribute($value)
    {
        if ($value) {
            $this->attributes['geo'] = DB::raw("ST_GeomFromGeoJSON('" . json_encode($value) . "')");
        } else {
            $this->attributes['geo'] = null;
        }
    }

    public function tipo()
    {
        return $this->belongsTo(TipoPatrimonio::class, 'tipo_patrimonio_id');
    }
}