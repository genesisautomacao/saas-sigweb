<?php

namespace App\Observers;

use App\Models\PessoaRenda;
use App\Services\Social\CadastroSocialCalculoService;

class PessoaRendaObserver
{
    public function __construct(private CadastroSocialCalculoService $calc) {}

    public function saved(PessoaRenda $renda): void
    {
        $this->calc->recalcularPorPessoa($renda->tenant_id, $renda->pessoa_id);
    }

    public function deleted(PessoaRenda $renda): void
    {
        $this->calc->recalcularPorPessoa($renda->tenant_id, $renda->pessoa_id);
    }
}
