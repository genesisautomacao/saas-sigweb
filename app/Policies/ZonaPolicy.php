<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Zona;
use Illuminate\Auth\Access\Response;

class ZonaPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_zonas'); }
    public function view(User $user, Zona $zona): bool { return $user->hasPermissionTo('view_zonas'); }
    // Como é Read-Only, você pode retornar false nas de criação/edição se quiser travar na raiz:
    public function create(User $user): bool { return false; }
    public function update(User $user, Zona $zona): bool { return false; }
    public function delete(User $user, Zona $zona): bool { return false; }
}
