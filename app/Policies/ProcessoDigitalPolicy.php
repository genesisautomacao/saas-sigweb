<?php
namespace App\Policies;
use App\Models\ProcessoDigital;
use App\Models\User;

class ProcessoDigitalPolicy
{
    public function viewAny(User $user): bool { return $user->can('view_processos_digitais'); }
    public function view(User $user, ProcessoDigital $model): bool { return $user->can('view_processos_digitais'); }
    public function create(User $user): bool { return $user->can('create_processos_digitais'); }
    public function update(User $user, ProcessoDigital $model): bool { return $user->can('edit_processos_digitais'); }
    public function delete(User $user, ProcessoDigital $model): bool { return $user->can('delete_processos_digitais'); }
}
