<?php

namespace App\Services\Social;

use App\Models\CadastroSocial;
use App\Models\MembroFamilia;
use App\Models\PessoaRenda;
use Illuminate\Support\Facades\DB;

/**
 * Cálculos automáticos da Família (itens PoC 096 e 097).
 * Usa DB::table para gravar sem re-disparar Observers (evita loop).
 */
class CadastroSocialCalculoService
{
    /** Salário mínimo de referência (ajustável). */
    public const SALARIO_MINIMO = 1518.00;

    public function recalcular(CadastroSocial $cadastro): void
    {
        if (!$cadastro->id) {
            return;
        }

        // IDs de pessoa: Responsável Familiar + membros
        $pessoaIds = collect([$cadastro->pessoa_id])
            ->merge(
                MembroFamilia::withoutGlobalScopes()
                    ->where('cadastro_social_id', $cadastro->id)
                    ->pluck('pessoa_id')
            )
            ->filter()->unique()->values();

        // 097 — Renda bruta familiar (só rendas que compõem) + per capita
        $rendaTotal = (float) PessoaRenda::withoutGlobalScopes()
            ->where('tenant_id', $cadastro->tenant_id)
            ->whereIn('pessoa_id', $pessoaIds)
            ->where('compoe_renda_familiar', true)
            ->sum('valor');

        $qtd = max(1, (int) $cadastro->quantidade_membros);
        $rendaPerCapita = round($rendaTotal / $qtd, 2);

        // 096 — Índice de vulnerabilidade (0–7)
        $indice = 0;
        if ($cadastro->em_area_de_risco) {
            $indice += 2;
        }
        if ($rendaPerCapita < (0.25 * self::SALARIO_MINIMO)) {
            $indice += 2;
        }
        if ($cadastro->possui_membro_com_deficiencia) {
            $indice += 1;
        }
        if (in_array($cadastro->situacao_moradia, ['ocupacao_irregular', 'situacao_de_rua'], true)) {
            $indice += 1;
        }
        if (!$cadastro->recebe_beneficios) {
            $indice += 1;
        }

        DB::table('cadastros_sociais')->where('id', $cadastro->id)->update([
            'renda_familiar_total'    => $rendaTotal,
            'renda_per_capita'        => $rendaPerCapita,
            'indice_vulnerabilidade'  => $indice,
        ]);
    }

    /** Recalcula todas as famílias afetadas por uma renda de pessoa. */
    public function recalcularPorPessoa(int $tenantId, int $pessoaId): void
    {
        // Famílias onde a pessoa é Responsável Familiar
        CadastroSocial::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('pessoa_id', $pessoaId)
            ->get()
            ->each(fn(CadastroSocial $c) => $this->recalcular($c));

        // Famílias onde a pessoa é membro
        $cadastroIds = MembroFamilia::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('pessoa_id', $pessoaId)
            ->pluck('cadastro_social_id');

        CadastroSocial::withoutGlobalScopes()
            ->whereIn('id', $cadastroIds)
            ->get()
            ->each(fn(CadastroSocial $c) => $this->recalcular($c));
    }
}
