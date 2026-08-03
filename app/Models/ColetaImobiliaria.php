<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Coleta de campo sobre uma entidade do cadastro imobiliário.
 *
 * Polimórfica: o cadastrador preenche itens do lote, das unidades e das edificações,
 * e outras camadas (árvore, poste...) podem virar coletáveis sem mudar o schema.
 *
 * `campanha` permite recadastrar sem perder o histórico da coleta anterior — antes,
 * quando isso vivia em colunas do lote, um recadastramento sobrescrevia a coleta antiga.
 */
class ColetaImobiliaria extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'coleta_imobiliaria';

    /** Status da coleta — lista estrutural: colore o mapa e alimenta a produtividade. */
    public const STATUS = [
        'nao_visitado' => 'Não visitado',
        'coletado' => 'Coletado',
        'pendente' => 'Pendente',
        'inconformidade' => 'Inconformidade',
    ];

    /** Campanha usada quando nenhuma outra é informada. */
    public const CAMPANHA_PADRAO = 'inicial';

    protected $fillable = [
        'tenant_id',
        'coletavel_type', 'coletavel_id',
        'campanha', 'status',
        'coletado_por_id', 'coletado_em',
        'observacao', 'inconformidade_descricao',
        'inconformidade_ponto',
    ];

    protected $casts = [
        'coletado_em' => 'datetime',
        'inconformidade_ponto' => 'array', // {lat, lon} marcado pelo app na vistoria
    ];

    public function coletavel(): MorphTo
    {
        return $this->morphTo();
    }

    /** withTrashed: o histórico de coleta precisa continuar exibindo o nome de quem coletou. */
    public function coletadoPor()
    {
        return $this->belongsTo(User::class, 'coletado_por_id')->withTrashed();
    }

    public function scopeDaCampanha($query, ?string $campanha = null)
    {
        return $query->where('campanha', $campanha ?? self::CAMPANHA_PADRAO);
    }

    /**
     * Registra (ou atualiza) a coleta de uma entidade na campanha informada.
     * Mantém `lotes.status_cadastro` sincronizado — ele é o cache que colore o mapa.
     */
    public static function registrar(
        Model $entidade,
        array $dados,
        ?string $campanha = null,
        ?int $tenantId = null
    ): self {
        $coleta = static::withoutGlobalScopes()->firstOrNew([
            'tenant_id' => $tenantId ?? $entidade->tenant_id,
            'coletavel_type' => $entidade->getMorphClass(),
            'coletavel_id' => $entidade->getKey(),
            'campanha' => $campanha ?? self::CAMPANHA_PADRAO,
        ]);

        $coleta->fill($dados)->save();

        if ($entidade instanceof Lote && filled($dados['status'] ?? null)) {
            // DB::table para não disparar os observers/log do Lote por causa do cache.
            \Illuminate\Support\Facades\DB::table('lotes')
                ->where('id', $entidade->getKey())
                ->update(['status_cadastro' => $dados['status']]);
        }

        return $coleta;
    }
}
