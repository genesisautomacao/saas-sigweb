<?php

namespace App\Observers;

use App\Models\CadastroSocial;
use App\Services\Social\CadastroSocialCalculoService;

class CadastroSocialObserver
{
    public function __construct(private CadastroSocialCalculoService $calc) {}

    public function saved(CadastroSocial $cadastro): void
    {
        // Recalcula renda e vulnerabilidade (grava via DB::table, não re-dispara evento)
        $this->calc->recalcular($cadastro);
    }
}
