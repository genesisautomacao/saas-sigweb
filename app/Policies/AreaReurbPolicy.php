<?php

namespace App\Policies;

use App\Models\{AreaReurb, User};

class AreaReurbPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('gerenciar_areas_reurb'); }
    public function view(User $user, AreaReurb $model): bool { return $user->hasPermissionTo('gerenciar_areas_reurb'); }
    public function create(User $user): bool { return $user->hasPermissionTo('gerenciar_areas_reurb'); }
    public function update(User $user, AreaReurb $model): bool { return $user->hasPermissionTo('gerenciar_areas_reurb'); }
    public function delete(User $user, AreaReurb $model): bool { return $user->hasPermissionTo('gerenciar_areas_reurb'); }
}
