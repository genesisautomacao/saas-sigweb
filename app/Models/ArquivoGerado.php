<?php

namespace App\Models;

use App\Support\MapaPermissoes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * R84-4 — arquivo pesado gerado em segundo plano (fila). Sem BelongsToTenant de
 * propósito: o mesmo registro aparece no /app (escopo pela prefeitura) e no /admin
 * (exportações pedidas pelo admin), e o job roda fora do Filament.
 */
class ArquivoGerado extends Model
{
    protected $table = 'arquivos_gerados';

    protected $guarded = ['id'];

    protected $casts = [
        'parametros' => 'array',
        'iniciado_em' => 'datetime',
        'concluido_em' => 'datetime',
        'expira_em' => 'datetime',
        'tamanho' => 'integer',
        'progresso' => 'integer',
    ];

    public const DIAS_VALIDADE = 7;

    public const STATUS = [
        'na_fila' => ['label' => 'Na fila', 'cor' => 'gray'],
        'processando' => ['label' => 'Processando', 'cor' => 'warning'],
        'pronto' => ['label' => 'Pronto', 'cor' => 'success'],
        'erro' => ['label' => 'Erro', 'cor' => 'danger'],
    ];

    public const TIPOS = [
        'coletas_pdf_detalhado' => 'PDF detalhado de coletas',
        'exportacao_dados' => 'Exportação de dados',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function pronto(): bool
    {
        return $this->status === 'pronto' && filled($this->caminho)
            && (! $this->expira_em || $this->expira_em->isFuture());
    }

    /**
     * Quem pode ver/baixar: o próprio autor; no /app o Manager da prefeitura vê todos
     * da prefeitura; no /admin o Master vê todos. Usado na lista E na rota de download.
     */
    public function visivelPara(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ((int) $this->user_id === (int) $user->id) {
            return true;
        }

        return $this->painel === 'admin'
            ? $user->isMaster()
            : MapaPermissoes::bypass($user, $this->tenant_id);
    }

    /** Lista do /app: os da prefeitura; usuário comum só os seus. */
    public function scopeVisiveisNoApp(Builder $query, User $user, int $tenantId): Builder
    {
        $query->where('painel', 'app')->where('tenant_id', $tenantId);

        return MapaPermissoes::bypass($user, $tenantId) ? $query : $query->where('user_id', $user->id);
    }

    /** Lista do /admin: Master vê todos; Operador só os seus. */
    public function scopeVisiveisNoAdmin(Builder $query, User $user): Builder
    {
        $query->where('painel', 'admin');

        return $user->isMaster() ? $query : $query->where('user_id', $user->id);
    }
}
