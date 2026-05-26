<?php
namespace App\Policies;
use App\Models\BpmnFluxo;
use App\Models\User;

class BpmnFluxoPolicy
{
    public function viewAny(User $user): bool { return $user->can('view_bpmn_fluxos'); }
    public function view(User $user, BpmnFluxo $model): bool { return $user->can('view_bpmn_fluxos'); }
    public function create(User $user): bool { return $user->can('create_bpmn_fluxos'); }
    public function update(User $user, BpmnFluxo $model): bool { return $user->can('edit_bpmn_fluxos'); }
    public function delete(User $user, BpmnFluxo $model): bool { return $user->can('delete_bpmn_fluxos'); }
}
