<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Traits\BelongsToTenant;
use App\Traits\HasTenantSequentialId;

class UnidadeImobiliaria extends Model
{
    use SoftDeletes, BelongsToTenant, HasTenantSequentialId;

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
        'logradouro_nome',
        'numero_imovel',
        // Campos fiscais promovidos do dados_tributarios (mesmo nome da chave JSON)
        'tipo_construcao',
        'descricao_classificacao',
        'face',
        'fracao_ideal',
        'area_edificacao',
        'area_total_edificacao',
        'valor_venal_lote',
        'valor_venal_edificacao',
        'valor_metro_terreno',
        'valor_metro_edificacao',
        'valor_imposto_territorial',
        'valor_imposto_predial',
        'valor_total_imposto',
    ];

    /**
     * Colunas fiscais que espelham chaves de mesmo nome dentro de dados_tributarios.
     * O JSON continua sendo a fonte bruta; estas colunas são derivadas dele.
     */
    public const CAMPOS_FISCAIS = [
        'tipo_construcao',
        'descricao_classificacao',
        'face',
        'fracao_ideal',
        'area_edificacao',
        'area_total_edificacao',
        'valor_venal_lote',
        'valor_venal_edificacao',
        'valor_metro_terreno',
        'valor_metro_edificacao',
        'valor_imposto_territorial',
        'valor_imposto_predial',
        'valor_total_imposto',
    ];

    protected $casts = [
        'dados_tributarios' => 'array',
    ];

    protected $hidden = ['geo'];
    protected $appends = ['geo_json'];

    protected static function booted(): void
    {
        // Propaga o endereço do JSON tributário para o Lote pai sempre que o
        // imóvel é sincronizado (import, simulação da API, edição da unidade).
        // Centraliza a herança dos campos usados na busca do cadastro de lotes.
        static::saved(function (UnidadeImobiliaria $unidade) {
            $unidade->propagarEnderecoParaLote();
            $unidade->sincronizarColunasFiscais();
        });
    }

    /**
     * Deriva as colunas fiscais (CAMPOS_FISCAIS) a partir do JSON dados_tributarios.
     * No sync tributário atualiza tudo; fora dele faz backfill apenas do que está vazio.
     * Usa DB::table para não re-disparar eventos do model (evita loop no saved).
     */
    public function sincronizarColunasFiscais(): void
    {
        $dt = $this->dados_tributarios;
        if (!is_array($dt) || empty($dt)) {
            return;
        }

        $mudouTributario = $this->wasRecentlyCreated || $this->wasChanged('dados_tributarios');

        $updates = [];
        foreach (self::CAMPOS_FISCAIS as $campo) {
            if (!array_key_exists($campo, $dt)) {
                continue;
            }
            $valor = ($dt[$campo] === null || $dt[$campo] === '') ? null : $dt[$campo];
            if ($mudouTributario || $this->getAttribute($campo) === null) {
                $updates[$campo] = $valor;
            }
        }

        if (!empty($updates)) {
            DB::table('unidade_imobiliarias')->where('id', $this->id)->update($updates);
        }
    }

    /**
     * Copia tipo_logradouro / logradouro / numero_logradouro / cep do JSON
     * tributário para o Lote pai.
     *
     * Regras:
     *  • Endereço puro (tipo/logradouro/cep): atualiza no sync tributário e
     *    também faz backfill quando o campo do lote está vazio.
     *  • numero_logradouro: segue o tributário no sync; no backfill só preenche
     *    se ainda estiver vazio e o gerador de numeração ainda não tiver rodado
     *    (numero_predial_antigo == null), para não sobrescrever número gerado.
     */
    public function propagarEnderecoParaLote(): void
    {
        if (!$this->lote_id) {
            return;
        }

        $dt = $this->dados_tributarios;
        if (!is_array($dt) || empty($dt)) {
            return;
        }

        $lote = DB::table('lotes')->where('id', $this->lote_id)
            ->first(['tipo_logradouro', 'logradouro', 'numero_logradouro', 'cep', 'numero_predial_antigo']);
        if (!$lote) {
            return;
        }

        // Houve sincronização real do tributário? (criação ou mudança do JSON)
        $mudouTributario = $this->wasRecentlyCreated || $this->wasChanged('dados_tributarios');

        $norm = fn($v) => ($v === null || $v === '') ? null : (string) $v;
        $updates = [];

        foreach (['tipo_logradouro', 'logradouro', 'cep'] as $campo) {
            if (!array_key_exists($campo, $dt)) {
                continue;
            }
            if ($mudouTributario || $lote->$campo === null) {
                $updates[$campo] = $norm($dt[$campo]);
            }
        }

        if (array_key_exists('numero_logradouro', $dt)) {
            $geradorRodou = $lote->numero_predial_antigo !== null;
            if ($mudouTributario) {
                $updates['numero_logradouro'] = $norm($dt['numero_logradouro']);
            } elseif ($lote->numero_logradouro === null && !$geradorRodou) {
                $updates['numero_logradouro'] = $norm($dt['numero_logradouro']);
            }
        }

        if (!empty($updates)) {
            DB::table('lotes')->where('id', $this->lote_id)->update($updates);
        }
    }

    public function getGeoJsonAttribute()
    {
        if (!isset($this->attributes['id']) || is_null($this->attributes['geo']))
            return null;
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

        $this->attributes['geo'] = DB::raw("ST_GeomFromGeoJSON('" . json_encode($value) . "')");
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
