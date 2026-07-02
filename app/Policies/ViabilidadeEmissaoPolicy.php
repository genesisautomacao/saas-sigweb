<?php

namespace App\Policies;

use App\Models\{ViabilidadeEmissao, User};

/**
 * Histórico de Emissões é somente leitura (o recurso só tem a página index +
 * ações de reimprimir/validar). Uma única permissão de visualização.
 */
class ViabilidadeEmissaoPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_viabilidade_emissoes'); }
    public function view(User $user, ViabilidadeEmissao $model): bool { return $user->hasPermissionTo('view_viabilidade_emissoes'); }
}
