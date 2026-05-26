<?php
namespace App\Policies;
use App\Models\Cnae;
use App\Models\User;

class CnaePolicy
{
    public function viewAny(User $user): bool { return $user->can('view_cnaes'); }
    public function view(User $user, Cnae $model): bool { return $user->can('view_cnaes'); }
    public function create(User $user): bool { return $user->can('create_cnaes'); }
    public function update(User $user, Cnae $model): bool { return $user->can('edit_cnaes'); }
    public function delete(User $user, Cnae $model): bool { return $user->can('delete_cnaes'); }
}
