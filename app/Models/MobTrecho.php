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
 * Trecho viário (Mobilidade Urbana — docs/piuma.txt §2.4/§3).
 *
 * SEM name (decisão 6.3): trecho urbano não tem nome — a referência é o
 * sequential_id. DIREÇÃO = ordem dos vértices da linha (início → fim);
 * `sentido` diz se a via é mão única/dupla e `azimute` é CALCULADO
 * (ST_Azimuth do 1º ao último vértice) — nunca digitado.
 * Os ~20 atributos de calçada/estacionamento/vegetação do levantamento
 * vivem em dados_customizados (kit Piúma — MobilidadeSeeder).
 */
class MobTrecho extends Model
{
    use BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges, SoftDeletes;

    public const SENTIDOS = [
        'mao_unica' => 'Mão única',
        'mao_dupla' => 'Mão dupla',
    ];

    /** Vocabulários das colunas estruturais (levantamento Piúma) — fonte única p/ mapa e Resource. */
    public const VOCABULARIOS = [
        'tipologia_da_via' => ['Avenida', 'Rua', 'Rodovia', 'Beco/Viela'],
        'tipo_de_pavimentacao' => ['Asfalto', 'Paralelepípedo', 'Terra (Solo natural)', 'Concreto', 'Outro'],
        'estado_conservacao_pavimentacao' => ['Ótimo', 'Bom', 'Regular', 'Ruim', 'Péssimo'],
        'classe_faixa_rodagem' => ['Pista Simples', 'Pista Dupla'],
        'dimensionamento_da_via' => ['Entre 0 - 2,99m', 'Entre 3,0m - 3,5m', 'Entre 3,6m - 6,99m', 'Entre 7,0 - 9,00m', 'Maior que 9,00m'],
    ];

    protected $table = 'mob_trechos';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'tipologia_da_via', 'tipo_de_pavimentacao', 'estado_conservacao_pavimentacao',
        'classe_faixa_rodagem', 'dimensionamento_da_via',
        'sentido', 'azimute', 'extensao_geo',
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
        return 'Geometria do trecho viário alterada';
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

    /**
     * Recalcula extensão (m) e azimute (0–360°, do 1º ao último vértice) a
     * partir da geometria atual — chamar após criar/editar geometria (mesmo
     * gatilho do extensao_geo nas traits Has*Actions) e na importação.
     */
    public function atualizarMetadataGeo(): void
    {
        try {
            DB::update(
                'UPDATE mob_trechos SET
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
        DB::update('UPDATE mob_trechos SET geo = ST_Reverse(geo) WHERE id = ?', [$this->id]);
        $this->atualizarMetadataGeo();
    }
}
