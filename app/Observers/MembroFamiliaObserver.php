<?php

namespace App\Observers;

use App\Models\MembroFamilia;
use App\Services\Social\CadastroSocialCalculoService;

class MembroFamiliaObserver
{
    public function __construct(private CadastroSocialCalculoService $calc) {}

    public function saved(MembroFamilia $membro): void
    {
        $this->recalc($membro);
    }

    public function deleted(MembroFamilia $membro): void
    {
        $this->recalc($membro);
    }

    private function recalc(MembroFamilia $membro): void
    {
        $cadastro = $membro->cadastroSocial;
        if ($cadastro) {
            $this->calc->recalcular($cadastro);
        }
    }
}
