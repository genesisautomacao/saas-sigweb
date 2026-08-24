<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Traits\LogsGeometryChanges;

class SecaoLogradouro extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId, LogsActivity, LogsGeometryChanges;

    /** Croqui Antes/Depois na Auditoria (PoC AC 2026-08-23). */
    public function geometryLogLabel(): string
    {
        return 'Geometria da seção de logradouro alterada';
    }

    /** Auditoria (PoC AC 2026-08-23): loga qualquer campo alterado; geo fica de fora. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['geo', 'created_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'secoes_logradouro';

    protected $fillable = [
        'tenant_id', 'sequential_id', 'code', 'codigo', 'lado',
        'name', 'extensao_geo',
        'logradouro_id',
        'dados_customizados',
        'geo',
    ];

    protected $casts = [
        'dados_customizados' => 'array', // campos do município (item 75)
    ];

    /**
     * T1.7 (item 17): fotos da seção — reusa a tabela polimórfica `documentos`
     * (mesmo padrão da UnidadeImobiliaria). A galeria da ficha do logradouro lê daqui.
     */
    public function documentos()
    {
        return $this->morphMany(Documento::class, 'documentable');
    }

    public function fotos()
    {
        return $this->documentos()->where('type', 'Foto');
    }

    /**
     * Item 44 do edital: "Código do Logradouro + Código da Seção (métrico)".
     * Ex.: logradouro 015, seção 0-120 → "015-0-120". Sem código do pai, sai só o da seção.
     */
    public function getCodigoCompostoAttribute(): ?string
    {
        $partes = array_filter([$this->logradouro?->codigo, $this->codigo]);

        return $partes ? implode('-', $partes) : null;
    }

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('secoes_logradouro')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])
            ->first();
        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        // Aceita LineString único e promove para MultiLineString (coluna é MULTILINESTRING)
        $this->attributes['geo'] = DB::raw("ST_Multi(ST_GeomFromGeoJSON('" . json_encode($value) . "'))");
    }

    public function logradouro()
    {
        return $this->belongsTo(Logradouro::class, 'logradouro_id');
    }
}
