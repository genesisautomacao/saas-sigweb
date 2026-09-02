<?php

namespace App\Policies;

use App\Models\{MobTipoSinalizacao, User};

class MobTipoSinalizacaoPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('gerenciar_mob_tipos_sinalizacao'); }
    public function view(User $user, MobTipoSinalizacao $model): bool { return $user->temPermissao('gerenciar_mob_tipos_sinalizacao'); }
    public function create(User $user): bool { return $user->temPermissao('gerenciar_mob_tipos_sinalizacao'); }
    public function update(User $user, MobTipoSinalizacao $model): bool { return $user->temPermissao('gerenciar_mob_tipos_sinalizacao'); }
    public function delete(User $user, MobTipoSinalizacao $model): bool { return $user->temPermissao('gerenciar_mob_tipos_sinalizacao'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('gerenciar_mob_tipos_sinalizacao'); }
}
