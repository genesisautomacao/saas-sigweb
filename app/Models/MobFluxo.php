<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use App\Traits\LogsGeometryChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Linha de desejo do estudo Origem/Destino (docs/piuma.txt §2.7).
 *
 * ⚠️ Semântica confirmada em 2026-09-04: o campo `fluxo` do levantamento nomeia
 * a ponta COMPARTILHADA de cada grupo de linhas = a ORIGEM (`origem_regiao`).
 * As zonas de origem e destino de cada linha são DERIVADAS da geometria
 * (`origem_zona` = zona O/D da ponta inicial, `destino_zona` = zona O/D da
 * ponta final) por recalcularOrigensDestinos() — nunca digitadas. `valores` =
 * volume de deslocamentos; no mapa a espessura é proporcional a ele e a cor é
 * por zona de DESTINO (distribuicao()).
 */
class MobFluxo extends Model
{
    use BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges, SoftDeletes;

    /** Rótulo das regiões de origem do levantamento (valores do campo `fluxo`). */
    public const REGIOES = [
        'centro' => 'Centro',
        'norte' => 'Norte',
        'sul' => 'Sul',
        'leste' => 'Leste',
        'nordeste' => 'Nordeste',
        'sudoeste' => 'Sudoeste',
        'oeste' => 'Oeste',
    ];

    /** Paleta das zonas de destino (atribuída por ordem de volume em distribuicao()). */
    public const PALETA = [
        '#dc2626', '#2563eb', '#16a34a', '#f59e0b', '#7c3aed',
        '#0891b2', '#db2777', '#65a30d', '#ea580c', '#4b5563',
    ];

    public const COR_SEM_ZONA = '#6b7280';

    protected $table = 'mob_fluxos';

    protected $fillable = [
        'tenant_id', 'sequential_id',
        'origem_regiao', 'origem_zona', 'destino_zona', 'valores',
        'dados_customizados', 'geo',
    ];

    protected $casts = [
        'dados_customizados' => 'array',
        'valores' => 'integer',
    ];

    protected $hidden = ['geo'];

    protected $appends = ['geo_json'];

    public static function slugZona(?string $zona): string
    {
        return $zona ? Str::slug($zona) : 'sem-zona';
    }

    public function origemRotulo(): string
    {
        return $this->origem_zona ?: (self::REGIOES[$this->origem_regiao] ?? ucfirst((string) $this->origem_regiao));
    }

    public function destinoRotulo(): string
    {
        return $this->destino_zona ?: 'Sem zona';
    }

    /**
     * Distribuição por zona de DESTINO do tenant (volume, % do total geral e cor)
     * — legenda do mapa, cor das linhas e ficha partem daqui. `total` inclui os
     * fluxos intrazonais (sem geometria): são deslocamentos reais.
     *
     * @return array{total:int, intrazonal:int, intrazonal_percentual:float, destinos: array<string, array{label:string, valores:int, percentual:float, cor:string}>}
     */
    public static function distribuicao(int $tenantId): array
    {
        $linhas = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->selectRaw('destino_zona, SUM(valores) AS valores')
            ->groupBy('destino_zona')
            ->orderByDesc('valores')
            ->orderBy('destino_zona')
            ->get();

        $total = (int) $linhas->sum('valores');

        // Intrazonais (origem = destino) não têm linha no mapa, mas contam no total —
        // a legenda avisa quanto do total "não aparece" como linha de desejo.
        // (a importação grava MULTILINESTRING EMPTY, não NULL — por isso o ST_IsEmpty)
        $intrazonal = (int) static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('geo')->orWhereRaw('ST_IsEmpty(geo)'))
            ->sum('valores');

        $destinos = [];
        $i = 0;
        foreach ($linhas as $l) {
            $chave = self::slugZona($l->destino_zona);
            $destinos[$chave] = [
                'label' => $l->destino_zona ?: 'Sem zona',
                'valores' => (int) $l->valores,
                'percentual' => $total > 0 ? round($l->valores * 100 / $total, 1) : 0.0,
                'cor' => $l->destino_zona ? self::PALETA[$i++ % count(self::PALETA)] : self::COR_SEM_ZONA,
            ];
        }

        return [
            'total' => $total,
            'intrazonal' => $intrazonal,
            'intrazonal_percentual' => $total > 0 ? round($intrazonal * 100 / $total, 1) : 0.0,
            'destinos' => $destinos,
        ];
    }

    /**
     * Deriva origem_zona/destino_zona de TODOS os fluxos do tenant a partir da
     * geometria e das zonas O/D (mob_zonas.tipo = zona_od): zona que contém a
     * ponta inicial (origem) e a final (destino); sem contenção, a mais próxima.
     * Intrazonais (sem geometria) herdam a zona de origem mais frequente do seu
     * grupo (`origem_regiao`) como origem E destino. Chamado pelo importador
     * (fluxos e zonas) e pelo comando mob:fluxos-origem-destino.
     *
     * @return int linhas com geometria atualizadas
     */
    public static function recalcularOrigensDestinos(int $tenantId): int
    {
        $zona = fn (string $ponto) => "COALESCE(
            (SELECT z.name FROM mob_zonas z
              WHERE z.tenant_id = f.tenant_id AND z.tipo = 'zona_od' AND z.deleted_at IS NULL AND z.geo IS NOT NULL
                AND ST_Contains(z.geo, {$ponto}) LIMIT 1),
            (SELECT z.name FROM mob_zonas z
              WHERE z.tenant_id = f.tenant_id AND z.tipo = 'zona_od' AND z.deleted_at IS NULL AND z.geo IS NOT NULL
              ORDER BY z.geo <-> {$ponto} LIMIT 1))";

        $inicio = 'ST_StartPoint(ST_GeometryN(f.geo, 1))';
        $fim = 'ST_EndPoint(ST_GeometryN(f.geo, ST_NumGeometries(f.geo)))';

        $comGeo = DB::update(
            "UPDATE mob_fluxos f SET origem_zona = {$zona($inicio)}, destino_zona = {$zona($fim)}
              WHERE f.tenant_id = ? AND f.deleted_at IS NULL AND f.geo IS NOT NULL AND NOT ST_IsEmpty(f.geo)",
            [$tenantId]
        );

        // Intrazonais (sem geometria): origem = destino = a zona O/D cujo NOME casa
        // com o rótulo do grupo (ex.: "sudoeste" → zona "Sudoeste", "centro" →
        // "Central"). ⚠️ Não usar "zona mais frequente do grupo": o grupo Sudoeste
        // tem 8 linhas saindo de Oeste e 8 de Sudoeste — empate que cada banco
        // desempatava de um jeito (VPS deu Oeste, local deu Sudoeste). Sem casar
        // pelo nome, cai na mais frequente com desempate por nome; por último, o rótulo.
        $zonasOd = DB::table('mob_zonas')
            ->where('tenant_id', $tenantId)->where('tipo', 'zona_od')->whereNull('deleted_at')
            ->orderBy('name')->pluck('name')->all();

        $maisFrequente = [];
        foreach (DB::table('mob_fluxos')
            ->where('tenant_id', $tenantId)->whereNull('deleted_at')->whereNotNull('origem_zona')
            ->whereRaw('geo IS NOT NULL AND NOT ST_IsEmpty(geo)')
            ->selectRaw('origem_regiao, origem_zona, COUNT(*) AS c')
            ->groupBy('origem_regiao', 'origem_zona')
            ->orderByDesc('c')->orderBy('origem_zona')
            ->get() as $g) {
            $maisFrequente[$g->origem_regiao] ??= $g->origem_zona;
        }

        $intrazonais = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('geo')->orWhereRaw('ST_IsEmpty(geo)'))
            ->get(['id', 'origem_regiao']);

        foreach ($intrazonais as $f) {
            $zona = self::zonaPeloRotulo($f->origem_regiao, $zonasOd)
                ?? $maisFrequente[$f->origem_regiao]
                ?? (self::REGIOES[$f->origem_regiao] ?? ($f->origem_regiao ? ucfirst($f->origem_regiao) : null));
            if ($zona) {
                DB::table('mob_fluxos')->where('id', $f->id)->update(['origem_zona' => $zona, 'destino_zona' => $zona]);
            }
        }

        return $comGeo;
    }

    /**
     * Zona O/D cujo nome corresponde ao rótulo da região do levantamento: igualdade
     * de slug ("sudoeste" = "Sudoeste") ou mesmo radical de 5 letras ("centro" ↔
     * "Central", "nordeste" ↔ "Nordeste"). Determinístico: lista ordenada por nome.
     *
     * @param  string[]  $zonasOd  nomes das zonas O/D do tenant
     */
    public static function zonaPeloRotulo(?string $regiao, array $zonasOd): ?string
    {
        if (! $regiao) {
            return null;
        }
        $alvo = Str::slug(self::REGIOES[$regiao] ?? $regiao);
        foreach ($zonasOd as $nome) {
            if (Str::slug($nome) === $alvo) {
                return $nome;
            }
        }
        if (strlen($alvo) >= 5) {
            foreach ($zonasOd as $nome) {
                if (str_starts_with(Str::slug($nome), substr($alvo, 0, 5))) {
                    return $nome;
                }
            }
        }

        return null;
    }

    public function geometryLogLabel(): string
    {
        return 'Geometria do fluxo O/D alterada';
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
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('".json_encode($value)."'))");
    }
}
