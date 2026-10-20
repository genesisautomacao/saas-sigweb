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
 * Setor Fiscal da INSCRIÇÃO imobiliária (B9, 2026-10-19) — base cartográfica.
 * `name` = número do setor que compõe a inscrição (texto: preserva "01").
 * Agrupa 1+ bairros (`bairros.setor_fiscal_id`). Distinto do SetorPgv (zona de
 * valor da PGV), que usava este nome até 2026-10-19.
 */
class SetorFiscal extends Model
{
    use BelongsToTenant, HasFactory, HasTenantSequentialId, LogsActivity, LogsGeometryChanges, SoftDeletes;

    protected $table = 'setores_fiscais';

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
        return 'Geometria do setor fiscal alterada';
    }

    public function getGeoJsonAttribute()
    {
        if (! isset($this->attributes['id']) || is_null($this->attributes['geo'] ?? null)) {
            return null;
        }

        $result = DB::table('setores_fiscais')
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

    public function bairros()
    {
        return $this->hasMany(Bairro::class, 'setor_fiscal_id');
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
            ->join('setores_fiscais as s', 's.id', '=', DB::raw((int) $this->id))
            ->where('b.tenant_id', $this->tenant_id)
            ->whereNull('b.deleted_at')
            ->whereNotNull('b.geo')
            ->whereRaw('ST_Contains(s.geo, ST_PointOnSurface(b.geo))')
            ->when(! $sobrescrever, fn ($q) => $q->whereNull('b.setor_fiscal_id'))
            ->where(fn ($q) => $q->whereNull('b.setor_fiscal_id')->orWhere('b.setor_fiscal_id', '<>', $this->id))
            ->pluck('b.id');

        $n = 0;
        foreach (Bairro::withoutGlobalScopes()->whereIn('id', $ids)->get() as $bairro) {
            $bairro->update(['setor_fiscal_id' => $this->id]);
            $n++;
        }

        return $n;
    }
}
