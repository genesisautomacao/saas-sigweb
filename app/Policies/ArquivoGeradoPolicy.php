<?php

namespace App\Policies;

use App\Models\ArquivoGerado;
use App\Models\User;

/**
 * R84-4 — "Arquivos Gerados": toda a equipe tem a tela (cada um vê os seus; o
 * escopo da lista faz o resto). Baixar/excluir = quem pode ver o arquivo.
 */
class ArquivoGeradoPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isCidadao();
    }

    public function view(User $user, ArquivoGerado $arquivo): bool
    {
        return $arquivo->visivelPara($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ArquivoGerado $arquivo): bool
    {
        return false;
    }

    public function delete(User $user, ArquivoGerado $arquivo): bool
    {
        return $arquivo->visivelPara($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
