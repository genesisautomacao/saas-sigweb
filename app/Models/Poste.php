<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Poste extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'postes';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'code',
        'address',
        'tipo_poste_id',
        'geo',
        'height',
        'installation_date',
        'structural_condition',
        'luminaire_type',
        'lamp_power',
        'luminaire_height',
        'lamp_quantity',
        'observations'
    ];

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    protected function casts(): array
    {
        return [
            'installation_date' => 'date',
        ];
    }

    /**
     * Extrai a geometria do PostGIS para o front-end
     */
    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('postes')
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

    public function tipoPoste(): BelongsTo
    {
        return $this->belongsTo(TipoPoste::class, 'tipo_poste_id');
    }

    public function logradouros(): BelongsToMany
    {
        return $this->belongsToMany(Logradouro::class, 'poste_logradouro');
    }
}