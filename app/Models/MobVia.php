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

/**
 * Via urbana (Mobilidade Urbana — docs/piuma.txt, Onda 6).
 *
 * É a entidade do FLUXO de tráfego: `sentido` (mão única/dupla) + DIREÇÃO =
 * ordem dos vértices da linha (mão única segue início → fim). `azimute` é
 * CALCULADO (ST_Azimuth 1º→último vértice), nunca digitado; inverterDirecao()
 * (ST_Reverse) é permitido AQUI — é a geometria da via, não do trecho de
 * levantamento (cuja direção define as calçadas e nunca pode virar).
 * Em Piúma cada via nasce 1:1 de um trecho (mob_trechos.via_id); em outro
 * município uma via pode agrupar vários trechos.
 */
class MobVia extends Model
{
    use BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges, SoftDeletes;

    public const SENTIDOS = [
        'mao_unica' => 'Mão única',
        'mao_dupla' => 'Mão dupla',
    ];

    protected $table = 'mob_vias';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'nome', 'sentido', 'azimute', 'extensao_geo',
        'logradouro_id', 'dados_customizados', 'geo',
    ];

    protected $casts = [
        'dados_customizados' => 'array',
        'azimute' => 'float',
    ];

    protected $hidden = ['geo'];

    protected $appends = ['geo_json'];

    public function geometryLogLabel(): string
    {
        return 'Geometria da via urbana alterada';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['geo', 'created_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getGeoJsonAttribute()
    {
        if (! isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table($this->table)
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();

        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        // Aceita LineString único e promove para MultiLineString
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('".json_encode($value)."'))");
    }

    public function logradouro()
    {
        return $this->belongsTo(Logradouro::class, 'logradouro_id');
    }

    /** Trechos de levantamento que compõem esta via (1:1 em Piúma). */
    public function trechos()
    {
        return $this->hasMany(MobTrecho::class, 'via_id');
    }

    /** Rótulo p/ telas e mapa. */
    public function rotulo(): string
    {
        return $this->nome ?: 'Via #'.$this->sequential_id;
    }

    /**
     * Recalcula extensão (m) e azimute (0–360°, do 1º ao último vértice) a
     * partir da geometria atual — chamar após criar/editar geometria.
     */
    public function atualizarMetadataGeo(): void
    {
        try {
            DB::update(
                'UPDATE mob_vias SET
                    extensao_geo = ST_Length(geo::geography),
                    azimute = degrees(ST_Azimuth(
                        ST_StartPoint(ST_GeometryN(geo, 1)),
                        ST_EndPoint(ST_GeometryN(geo, ST_NumGeometries(geo)))
                    ))
                 WHERE id = ? AND geo IS NOT NULL',
                [$this->id]
            );
        } catch (\Throwable $e) {
            // Tolerante a ambientes sem a coluna/PostGIS — mesmo padrão das demais entidades
        }
    }

    /**
     * Inverte a direção da linha (ST_Reverse) — a direção É a ordem dos
     * vértices, então inverter a geometria inverte o fluxo da mão única.
     */
    public function inverterDirecao(): void
    {
        DB::update('UPDATE mob_vias SET geo = ST_Reverse(geo) WHERE id = ?', [$this->id]);
        $this->atualizarMetadataGeo();
    }
}
