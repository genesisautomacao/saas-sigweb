<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use App\Traits\LogsGeometryChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Lote extends Model
{
    use BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        // Auditoria completa (PoC AC 2026-08-23): qualquer campo alterado entra
        // no log — geo fica com o LogsGeometryChanges (croqui Antes/Depois).
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['geo', 'created_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** Histórico cartográfico (B13 / item 044). */
    public function geometryLogLabel(): string
    {
        return 'Geometria do lote alterada';
    }

    protected $fillable = [
        'tenant_id', 'sequential_id', 'quadra_id', 'zona_id', 'code',
        'numero_lote', 'numero_predial_antigo', 'numero_logradouro',
        'area_geo', 'area_cadastrada', 'main_facade_length',
        'foto_frontal', 'foto_lateral_esq', 'foto_lateral_dir',
        'status_cadastro', 'ocupacao',
        'dados_customizados',
        'geo',
    ];

    protected $casts = [
        'dados_customizados' => 'array', // R67-1 — campos customizados do município
    ];

    protected $hidden = ['geo'];

    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute()
    {
        if (! isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        // O 6 mantém o arquivo JSON leve sem perder precisão
        $result = DB::table('lotes')
            ->select(DB::raw('ST_AsGeoJSON(geo, 6) as geo_json'))
            ->where('id', $this->attributes['id'])->first();

        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('".json_encode($value)."'))");
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

    public function testadas()
    {
        return $this->hasMany(LoteTestada::class, 'lote_id');
    }

    /**
     * Coletas de campo deste lote (todas as campanhas).
     * O status da coleta vigente fica cacheado em `status_cadastro` (cor do mapa).
     */
    public function coletas()
    {
        return $this->morphMany(ColetaImobiliaria::class, 'coletavel');
    }

    /** Coleta vigente (campanha padrão) — substitui as antigas colunas coletado_*. */
    public function coletaVigente()
    {
        return $this->morphOne(ColetaImobiliaria::class, 'coletavel')
            ->where('campanha', ColetaImobiliaria::CAMPANHA_PADRAO);
    }
}
