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

class UnidadeImobiliaria extends Model
{
    use BelongsToTenant, HasTenantSequentialId, SoftDeletes, LogsActivity, LogsGeometryChanges;

    /** Croqui Antes/Depois na Auditoria (PoC AC 2026-08-23). */
    public function geometryLogLabel(): string
    {
        return 'Geometria da unidade imobiliária alterada';
    }

    /**
     * Auditoria (PoC AC 2026-08-23): loga qualquer campo alterado. `dados_tributarios`
     * fica de fora (a sincronização tributária em massa inundaria o log com JSONs
     * gigantes — as 13 colunas fiscais promovidas são logadas individualmente).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['geo', 'created_at', 'updated_at', 'dados_tributarios'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'tenant_id',
        'sequential_id',
        'lote_id',
        'codigo_imovel_tributario',
        'inscricao_imobiliaria',
        'proprietario_id',
        'code',
        'geo',
        'dados_tributarios',
        'dados_customizados',
        'logradouro_nome',
        'numero_imovel',
        'nome_edificio',
    ];

    /**
     * As 13 colunas fiscais promovidas em 2026-07-01 foram REMOVIDAS na refatoração da
     * PoC Tangará (lista aprovada em docs/campos_imobiliario_para_aprovacao.txt):
     * nenhuma tinha consumidor no código além de exibição — busca e PGV sempre leram o
     * JSON. O dado segue íntegro em `dados_tributarios`; o que o município quiser VER
     * vira campo customizado alimentado pelo de/para da integração.
     */

    protected $casts = [
        'dados_tributarios' => 'array',
        'dados_customizados' => 'array', // R67-1 — campos customizados do município
    ];

    protected $hidden = ['geo'];

    protected $appends = ['geo_json'];

    protected static function booted(): void
    {
        // Deriva do JSON tributário o que ainda tem coluna própria (nome_edificio) e o
        // número predial do lote. O endereço textual do lote (tipo/logradouro/cep) foi
        // REMOVIDO — era cópia da unidade e a busca já lê a unidade diretamente.
        static::saved(function (UnidadeImobiliaria $unidade) {
            $unidade->propagarNumeroPredialParaLote();
            $unidade->sincronizarNomeEdificio();
        });
    }

    /** Deriva `nome_edificio` do JSON quando a importação/sync o trouxer. */
    public function sincronizarNomeEdificio(): void
    {
        $dt = $this->dados_tributarios;
        if (! is_array($dt) || ! array_key_exists('nome_edificio', $dt)) {
            return;
        }

        $mudouTributario = $this->wasRecentlyCreated || $this->wasChanged('dados_tributarios');
        $valor = ($dt['nome_edificio'] === null || $dt['nome_edificio'] === '') ? null : (string) $dt['nome_edificio'];

        if ($mudouTributario || $this->getAttribute('nome_edificio') === null) {
            // DB::table para não re-disparar o saved (evita loop).
            DB::table('unidade_imobiliarias')->where('id', $this->id)->update(['nome_edificio' => $valor]);
        }
    }

    /**
     * Propaga o número do imóvel para `lotes.numero_logradouro` — o número predial
     * ATUAL do lote. Não sobrescreve número já gerado pela ferramenta de numeração
     * predial (numero_predial_antigo preenchido = o gerador rodou).
     */
    public function propagarNumeroPredialParaLote(): void
    {
        if (! $this->lote_id) {
            return;
        }

        $dt = $this->dados_tributarios;
        if (! is_array($dt) || ! array_key_exists('numero_logradouro', $dt)) {
            return;
        }

        $lote = DB::table('lotes')->where('id', $this->lote_id)
            ->first(['numero_logradouro', 'numero_predial_antigo']);
        if (! $lote) {
            return;
        }

        $mudouTributario = $this->wasRecentlyCreated || $this->wasChanged('dados_tributarios');
        $valor = ($dt['numero_logradouro'] === null || $dt['numero_logradouro'] === '') ? null : (string) $dt['numero_logradouro'];
        $geradorRodou = $lote->numero_predial_antigo !== null;

        if (($mudouTributario && ! $geradorRodou)
            || ($lote->numero_logradouro === null && ! $geradorRodou)) {
            DB::table('lotes')->where('id', $this->lote_id)->update(['numero_logradouro' => $valor]);
        }
    }

    public function getGeoJsonAttribute()
    {
        if (! isset($this->attributes['id']) || is_null($this->attributes['geo'])) {
            return null;
        }
        $result = DB::table('unidade_imobiliarias')
            ->select(DB::raw('ST_AsGeoJSON(geo) as geo_json'))
            ->where('id', $this->attributes['id'])->first();

        return $result ? json_decode($result->geo_json) : null;
    }

    public function setGeoAttribute($value)
    {
        // Se a geometria for nula (Ex: apartamento sem ponto no mapa), salva como NULL puro
        if (empty($value)) {
            $this->attributes['geo'] = null;

            return;
        }

        $this->attributes['geo'] = DB::raw("ST_GeomFromGeoJSON('".json_encode($value)."')");
    }

    public function lote()
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }

    /**
     * Uma Unidade Imobiliária pertence a um Proprietário (Pessoa)
     */
    public function proprietario()
    {
        return $this->belongsTo(\App\Models\Pessoa::class, 'proprietario_id');
    }

    public function documentos()
    {
        return $this->morphMany(Documento::class, 'documentable');
    }
}
