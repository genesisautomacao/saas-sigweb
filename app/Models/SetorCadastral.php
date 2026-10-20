<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use App\Traits\LogsGeometryChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Setor Cadastral da INSCRIÇÃO imobiliária (B9, 2026-10-19) — base cartográfica.
 * `name` = número do setor que compõe a inscrição (texto: preserva "01").
 * Agrupa 1+ bairros (`bairros.setor_cadastral_id`). Distinto do SetorPgv (zona de
 * valor da PGV). Nasceu na R85 como `setores_fiscais` (SetorFiscal) e foi renomeado
 * em 2026-10-20 a pedido da engenharia da Líder.
 */
class SetorCadastral extends Model
{
    use BelongsToTenant, HasFactory, HasTenantSequentialId, LogsActivity, LogsGeometryChanges, SoftDeletes;

    protected $table = 'setores_cadastrais';

    protected $fillable = ['tenant_id', 'sequential_id', 'name', 'dados_customizados', 'geo', 'area_geo'];

    protected $casts = [
        'dados_customizados' => 'array', // campos do município (item 75)
    ];

    protected $hidden = ['geo'];

    protected $appends = ['geo_json'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['geo', 'created_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function geometryLogLabel(): string
    {
        return 'Geometria do setor cadastral alterada';
    }

    public function getGeoJsonAttribute()
    {
        if (! isset($this->attributes['id']) || is_null($this->attributes['geo'] ?? null)) {
            return null;
        }

        $result = DB::table('setores_cadastrais')
            ->select(DB::raw('ST_AsGeoJSON(geo, 6) as geo_json'))
            ->where('id', $this->attributes['id'])->first();

        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        $this->attributes['geo'] = $value
            ? DB::raw("ST_Multi(ST_GeomFromGeoJSON('".json_encode($value)."'))")
            : null;
    }

    /**
     * B10 (2026-10-20) — tira do polígono a parte que invade os setores vizinhos
     * vivos da prefeitura: a divisa fica exatamente colada no vizinho. Farelos
     * menores que 1 m² que sobram do recorte são descartados.
     *
     * Retorna ['geo' => GeoJSON MultiPolygon|null, 'area' => m², 'recortado' => m²,
     * 'vizinhos' => nomes recortados, 'erro' => motivo do bloqueio|null]. Bloqueia
     * quando o desenho cai inteiro dentro de outro setor ou engole um vizinho
     * inteiro (aí é engano, não ajuste de divisa).
     */
    public static function recortarPelosVizinhos(array $geoJson, int $tenantId, ?int $ignorarId = null): array
    {
        $r = DB::selectOne('
            WITH novo AS (SELECT ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)) AS g),
            viz AS (
                SELECT s.name, s.geo FROM setores_cadastrais s, novo
                WHERE s.tenant_id = ? AND s.deleted_at IS NULL AND s.id <> ?
                  AND s.geo IS NOT NULL AND ST_Intersects(s.geo, novo.g)
            ),
            uniao AS (SELECT ST_Union(geo) AS g FROM viz),
            partes AS (
                SELECT (ST_Dump(ST_CollectionExtract(ST_MakeValid(
                    CASE WHEN uniao.g IS NULL THEN novo.g ELSE ST_Difference(novo.g, uniao.g) END
                ), 3))).geom AS p
                FROM novo, uniao
            ),
            fica AS (SELECT ST_Union(p) AS g FROM partes WHERE ST_Area(p::geography) >= 1)
            SELECT
                (SELECT ST_AsGeoJSON(ST_Multi(g), 15) FROM fica WHERE g IS NOT NULL) AS gj,
                (SELECT COALESCE(ST_Area(g::geography), 0) FROM fica) AS area,
                (SELECT COALESCE(ST_Area(ST_Intersection(novo.g, uniao.g)::geography), 0) FROM novo, uniao) AS recortado,
                (SELECT string_agg(viz.name, \', \' ORDER BY viz.name) FROM viz, novo
                    WHERE ST_Area(ST_Intersection(viz.geo, novo.g)::geography) >= 0.5) AS vizinhos,
                (SELECT string_agg(viz.name, \', \' ORDER BY viz.name) FROM viz, novo
                    WHERE ST_Covers(novo.g, viz.geo)) AS engolidos
        ', [json_encode($geoJson), $tenantId, $ignorarId ?? 0]);

        $saida = [
            'geo' => $r?->gj ? json_decode($r->gj, true) : null,
            'area' => round((float) ($r->area ?? 0), 2),
            'recortado' => round((float) ($r->recortado ?? 0), 2),
            'vizinhos' => $r->vizinhos ?? '',
            'erro' => null,
        ];

        if ($r?->engolidos) {
            $saida['erro'] = "O desenho engloba inteiro o setor {$r->engolidos}. Ajuste a divisa em vez de cobrir o vizinho.";
        } elseif (! $saida['geo']) {
            $saida['erro'] = 'O desenho fica todo dentro de outro setor ('.($saida['vizinhos'] ?: 'vizinho').'); não sobra área para este setor.';
        }

        return $saida;
    }

    /** Texto do aviso de recorte, ou null quando nada foi recortado. */
    public static function avisoRecorte(array $recorte): ?string
    {
        if ($recorte['recortado'] < 0.01) {
            return null;
        }
        $m2 = number_format($recorte['recortado'], $recorte['recortado'] < 10 ? 2 : 0, ',', '.');

        return "{$m2} m² que invadiam o setor {$recorte['vizinhos']} foram recortados: a divisa ficou colada no vizinho.";
    }

    public function bairros()
    {
        return $this->hasMany(Bairro::class, 'setor_cadastral_id');
    }

    /**
     * Liga ao setor os bairros cujo PONTO INTERNO cai dentro dele (mesma regra do
     * recorte por bairro do "Exportar dados": um bairro nunca cai em dois setores).
     * $sobrescrever = false só preenche bairro sem setor. Grava via Eloquent para
     * a troca entrar na Auditoria. Retorna quantos bairros mudaram.
     */
    public function vincularBairrosPelaGeometria(bool $sobrescrever = false): int
    {
        $ids = DB::table('bairros as b')
            ->join('setores_cadastrais as s', 's.id', '=', DB::raw((int) $this->id))
            ->where('b.tenant_id', $this->tenant_id)
            ->whereNull('b.deleted_at')
            ->whereNotNull('b.geo')
            ->whereRaw('ST_Contains(s.geo, ST_PointOnSurface(b.geo))')
            ->when(! $sobrescrever, fn ($q) => $q->whereNull('b.setor_cadastral_id'))
            ->where(fn ($q) => $q->whereNull('b.setor_cadastral_id')->orWhere('b.setor_cadastral_id', '<>', $this->id))
            ->pluck('b.id');

        $n = 0;
        foreach (Bairro::withoutGlobalScopes()->whereIn('id', $ids)->get() as $bairro) {
            $bairro->update(['setor_cadastral_id' => $this->id]);
            $n++;
        }

        return $n;
    }
}
