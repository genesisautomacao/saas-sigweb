<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Arvore extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasTenantSequentialId;

    protected $table = 'arvores';

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'code',
        'address', 
        'botanical_species', 
        'botanical_family',
        'phytosanitary_condition',
        'size',
        'trunk_diameter_dap',
        'canopy_diameter',
        'total_height',
        'canopy_height',
        'general_state',
        'root_system',
        'urban_interferences',
        'risk_potential',
        'observations',
        'geo'
    ];

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
        $result = DB::table('arvores')
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

    /**
     * Relacionamento com as ruas (Radar PostGIS)
     */
    public function logradouros(): BelongsToMany
    {
        return $this->belongsToMany(Logradouro::class, 'arvore_logradouro');
    }

    /**
     * Histórico de Solicitações (Polimórfico)
     */
    public function solicitacoesManutencao()
    {
        return $this->morphMany(SolicitacaoManutencao::class, 'asset');
    }

    /**
     * Histórico de Ordens de Serviço (Polimórfico)
     */
    public function ordensServico()
    {
        return $this->morphMany(OrdemServico::class, 'asset');
    }
}