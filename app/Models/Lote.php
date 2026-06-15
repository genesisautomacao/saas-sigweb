<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Lote extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['numero_lote', 'status_cadastro', 'ocupacao', 'situacao_quadra', 'observacao'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'tenant_id', 'sequential_id', 'quadra_id', 'zona_id', 'code',
        'numero_lote', 'area_geo', 'area_cadastrada', 'main_facade_length',
        'foto_frontal', 'foto_lateral_esq', 'foto_lateral_dir',
        'observacao', 'status_cadastro', 'ocupacao', 'situacao_quadra',
        'inconformidade_descricao', 'dados_vistoria',
        'coletado_por_id', 'coletado_em',
        'geo',
    ];

    protected $casts = [
        'dados_vistoria' => 'array',
        'coletado_em'    => 'datetime',
    ];
    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo']))
            return null;
        // O 6 mantém o arquivo JSON leve sem perder precisão
        $result = DB::table('lotes')
            ->select(DB::raw('ST_AsGeoJSON(geo, 6) as geo_json'))
            ->where('id', $this->attributes['id'])->first();
        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('" . json_encode($value) . "'))");
    }

    /**
     * Um Lote pertence a uma Zona (Necessário para a Viabilidade)
     */
    public function zona()
    {
        return $this->belongsTo(Zona::class, 'zona_id');
    }

    /**
     * Um Lote pertence a uma Quadra
     */
    public function quadra()
    {
        return $this->belongsTo(Quadra::class, 'quadra_id');
    }

    /**
     * Um Lote possui várias Unidades Imobiliárias
     */
    public function unidadesImobiliarias()
    {
        return $this->hasMany(UnidadeImobiliaria::class, 'lote_id');
    }

    /**
     * Um Lote pode possuir várias Edificações
     */
    public function edificacoes()
    {
        return $this->hasMany(Edificacao::class, 'lote_id');
    }

    /**
     * Quem coletou este lote em campo (preenchido pelo mobile via push)
     */
    public function coletor()
    {
        return $this->belongsTo(User::class, 'coletado_por_id');
    }
}