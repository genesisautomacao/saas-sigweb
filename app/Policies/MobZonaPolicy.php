<?php

namespace App\Policies;

use App\Models\{MobZona, User};

class MobZonaPolicy
{
    public function viewAny(User $user): bool { return $user->temPermissao('gerenciar_mob_zonas'); }
    public function view(User $user, MobZona $model): bool { return $user->temPermissao('gerenciar_mob_zonas'); }
    public function create(User $user): bool { return $user->temPermissao('gerenciar_mob_zonas'); }
    public function update(User $user, MobZona $model): bool { return $user->temPermissao('gerenciar_mob_zonas'); }
    public function delete(User $user, MobZona $model): bool { return $user->temPermissao('gerenciar_mob_zonas'); }
    public function deleteAny(User $user): bool { return $user->temPermissao('gerenciar_mob_zonas'); }
    public function restore(User $user, MobZona $model): bool { return $user->temPermissao('gerenciar_mob_zonas'); }
    public function forceDelete(User $user, MobZona $model): bool { return $user->temPermissao('gerenciar_mob_zonas'); }
}
