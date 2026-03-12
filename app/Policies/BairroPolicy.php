<?php

namespace App\Policies;

use App\Models\Bairro;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BairroPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_bairros'); }
    public function view(User $user, Bairro $bairro): bool { return $user->hasPermissionTo('view_bairros'); }
    // Como é Read-Only, você pode retornar false nas de criação/edição se quiser travar na raiz:
    public function create(User $user): bool { return false; }
    public function update(User $user, Bairro $bairro): bool { return false; }
    public function delete(User $user, Bairro $bairro): bool { return false; }
}
