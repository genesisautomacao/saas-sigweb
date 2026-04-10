<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PontoPanoramico extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'pontos_panoramicos';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'code',
        'titulo',
        'image_path',
        'image_url_simulacao',
        'data_captura',
        'geo',
    ];

        protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->code)) {
                $model->code = (string) Str::uuid();
            }
        });
    }

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    /**
     * Extrai a geometria do PostGIS para o front-end
     */
    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('pontos_panoramicos')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();
            
        return $result ? json_decode($result->geo_json) : null;
    }

    /**
     * Salva a geometria recebida do mapa no PostGIS
     */
    public function setGeoAttribute($value)
    {
        if ($value) {
            $this->attributes['geo'] = DB::raw("ST_GeomFromGeoJSON('" . json_encode($value) . "')");
        } else {
            $this->attributes['geo'] = null;
        }
    }
}