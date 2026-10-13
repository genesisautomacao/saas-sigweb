<?php

namespace App\Policies;

use App\Models\MobSinalizacao;
use App\Models\User;

/** R83 (2026-10-12): quarteto no lugar de gerenciar_mob_sinalizacoes. temPermissao = falha fechada (incidente 2026-08-05). */
class MobSinalizacaoPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('view_mob_sinalizacoes'); }
    public function view(User $user, MobSinalizacao $model): bool { return $user->temPermissao('view_mob_sinalizacoes'); }
    public function create(User $user): bool { return $user->temPermissao('create_mob_sinalizacoes'); }
    public function update(User $user, MobSinalizacao $model): bool { return $user->temPermissao('edit_mob_sinalizacoes'); }
    public function delete(User $user, MobSinalizacao $model): bool { return $user->temPermissao('delete_mob_sinalizacoes'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('delete_mob_sinalizacoes'); }
    public function restore(User $user, MobSinalizacao $model): bool { return $user->temPermissao('delete_mob_sinalizacoes'); }
    public function forceDelete(User $user, MobSinalizacao $model): bool { return $user->temPermissao('delete_mob_sinalizacoes'); }
}
