<?php

namespace App\Policies;

use App\Models\Loteamento;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LoteamentoPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermissionTo('view_loteamentos'); }
    public function view(User $user, Loteamento $loteamento): bool { return $user->hasPermissionTo('view_loteamentos'); }
    // Como é Read-Only, você pode retornar false nas de criação/edição se quiser travar na raiz:
    public function create(User $user): bool { return false; }
    public function update(User $user, Loteamento $loteamento): bool { return false; }
    public function delete(User $user, Loteamento $loteamento): bool { return false; }
}
