<?php

namespace Database\Seeders;

use App\Models\BpmnEtapa;
use App\Models\BpmnFluxo;
use App\Models\Setor;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeds de fluxos BPMN pré-configurados do motor de Processos Digitais (item B17 / TR 138–148, 205).
 *
 * Materializa "Aprovação de Projeto", "Habite-se" e "REURB" como FLUXOS do MESMO motor
 * (não módulos separados — ver processosConceito.md §1), usando todo o motor híbrido:
 * etapas com `executor` (solicitante|analista), `setor_id`, `ordem`, formulários por etapa
 * (4 tipos) e `exige_imovel` no fluxo.
 *
 * Uso:
 *   PROCESSO_SEED_TENANT=<slug> php artisan db:seed --class=ProcessoFluxoExemploSeeder
 *   (sem slug: todos os tenants com o módulo 'processos')
 *
 * Idempotente: pula fluxos cujo nome já exista no tenant.
 */
class ProcessoFluxoExemploSeeder extends Seeder
{
    public function run(): void
    {
        $slug = env('PROCESSO_SEED_TENANT');

        $tenants = $slug
            ? Tenant::where('slug', $slug)->get()
            : Tenant::all()->filter(fn ($t) => in_array('processos', $t->modules ?? []));

        if ($tenants->isEmpty()) {
            $this->command->warn('Nenhum tenant alvo (defina PROCESSO_SEED_TENANT ou ative o módulo "processos").');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->command->info("→ Semeando fluxos para: {$tenant->name}");
            $setores = $this->criarSetores($tenant);
            $this->fluxoAprovacaoProjeto($tenant, $setores);
            $this->fluxoHabitese($tenant, $setores);
            $this->fluxoReurb($tenant, $setores);
        }
    }

    /** @return array<string,int> nome => id do setor */
    private function criarSetores(Tenant $tenant): array
    {
        $defs = [
            'Protocolo Geral'   => '#64748b',
            'Obras e Urbanismo' => '#f59e0b',
            'Procuradoria'      => '#8b5cf6',
            'Gabinete'          => '#16a34a',
        ];

        $mapa = [];
        foreach ($defs as $nome => $cor) {
            $setor = Setor::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('nome', $nome)
                ->first();

            if (!$setor) {
                $setor = new Setor();
                $setor->tenant_id = $tenant->id;
                $setor->nome = $nome;
                $setor->cor = $cor;
                $setor->save();
            }
            $mapa[$nome] = $setor->id;
        }

        return $mapa;
    }

    private function criarFluxo(Tenant $tenant, string $nome, string $descricao, bool $exigeImovel, array $etapas): void
    {
        $existe = BpmnFluxo::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('nome', $nome)
            ->exists();

        if ($existe) {
            $this->command->line("   • Fluxo já existe, pulando: {$nome}");
            return;
        }

        $fluxo = new BpmnFluxo();
        $fluxo->tenant_id = $tenant->id;
        $fluxo->nome = $nome;
        $fluxo->descricao = $descricao;
        $fluxo->ativo = true;
        $fluxo->modo_imovel = $exigeImovel ? 'mapa' : 'nenhum';
        $fluxo->perfis_autorizados = null; // aberto a todos os perfis
        $fluxo->save();

        foreach ($etapas as $i => $e) {
            $etapa = new BpmnEtapa();
            $etapa->tenant_id = $tenant->id;
            $etapa->bpmn_fluxo_id = $fluxo->id;
            $etapa->nome = $e['nome'];
            $etapa->executor = $e['executor'];
            $etapa->setor_id = $e['setor_id'] ?? null;
            $etapa->ordem = $i + 1;
            $etapa->cor_mapa = $e['cor'] ?? '#f59e0b';
            $etapa->tempo_medio_minutos = $e['sla'] ?? 0;
            // usuarios_autorizados vazio = todos os usuários do setor (item 5)
            $etapa->usuarios_autorizados = null;
            $etapa->campos_formulario = $e['campos'] ?? [];
            $etapa->save();
        }

        $this->command->line("   ✓ Fluxo criado: {$nome} (" . count($etapas) . ' etapas)');
    }

    // ---- Blocos de campo (formato do Builder campos_formulario) ----

    private function texto(string $label, bool $obrig = false): array
    {
        return ['type' => 'texto', 'data' => ['label_campo' => $label, 'obrigatorio' => $obrig]];
    }

    private function checkbox(string $label, array $opcoes, bool $obrig = false): array
    {
        return ['type' => 'checkbox', 'data' => ['label_campo' => $label, 'opcoes' => $opcoes, 'obrigatorio' => $obrig]];
    }

    private function mapa(string $label, bool $obrig = false): array
    {
        return ['type' => 'mapa', 'data' => ['label_campo' => $label, 'obrigatorio' => $obrig]];
    }

    private function documento(string $label, string $mascara, bool $obrig = false): array
    {
        return ['type' => 'documento', 'data' => ['label_campo' => $label, 'mascara' => $mascara, 'obrigatorio' => $obrig]];
    }

    // ---- Fluxos ----

    private function fluxoAprovacaoProjeto(Tenant $tenant, array $s): void
    {
        $this->criarFluxo($tenant, 'Aprovação de Projeto de Construção/Reforma',
            'Protocolo → Análise Técnica → Correções → Alvará', true, [
                ['nome' => 'Abertura do Pedido', 'executor' => 'solicitante', 'setor_id' => $s['Protocolo Geral'], 'cor' => '#64748b', 'campos' => [
                    $this->texto('Descrição da obra', true),
                    $this->documento('CPF do responsável técnico', 'cpf', true),
                    $this->checkbox('Tipo de obra', ['Construção', 'Reforma', 'Ampliação'], true),
                ]],
                ['nome' => 'Análise Técnica', 'executor' => 'analista', 'setor_id' => $s['Obras e Urbanismo'], 'cor' => '#f59e0b', 'sla' => 5, 'campos' => [
                    $this->checkbox('Checklist da análise', ['Planta aprovada', 'ART/RRT', 'Recuos conformes', 'Taxa de ocupação OK'], false),
                    $this->texto('Parecer técnico', true),
                ]],
                ['nome' => 'Correções do Solicitante', 'executor' => 'solicitante', 'setor_id' => $s['Protocolo Geral'], 'cor' => '#ef4444', 'campos' => [
                    $this->texto('Descrição das correções realizadas', true),
                ]],
                ['nome' => 'Emissão do Alvará', 'executor' => 'analista', 'setor_id' => $s['Gabinete'], 'cor' => '#16a34a', 'sla' => 2, 'campos' => [
                    $this->texto('Número do Alvará', true),
                ]],
            ]);
    }

    private function fluxoHabitese(Tenant $tenant, array $s): void
    {
        $this->criarFluxo($tenant, 'Habite-se Online / Atestado de Conclusão de Obra',
            'Solicitação → Vistoria → Emissão do Habite-se', true, [
                ['nome' => 'Solicitação de Habite-se', 'executor' => 'solicitante', 'setor_id' => $s['Protocolo Geral'], 'cor' => '#64748b', 'campos' => [
                    $this->documento('Telefone de contato', 'telefone', true),
                    $this->checkbox('Documentos anexados', ['Projeto aprovado', 'Fotos da obra concluída'], false),
                ]],
                ['nome' => 'Vistoria', 'executor' => 'analista', 'setor_id' => $s['Obras e Urbanismo'], 'cor' => '#f59e0b', 'sla' => 7, 'campos' => [
                    $this->mapa('Ponto da vistoria', false),
                    $this->checkbox('Situação da obra', ['Conforme o projeto', 'Não conforme'], true),
                    $this->texto('Observações da vistoria', false),
                ]],
                ['nome' => 'Emissão do Habite-se', 'executor' => 'analista', 'setor_id' => $s['Gabinete'], 'cor' => '#16a34a', 'sla' => 2, 'campos' => [
                    $this->texto('Número do Habite-se', true),
                ]],
            ]);
    }

    private function fluxoReurb(Tenant $tenant, array $s): void
    {
        $this->criarFluxo($tenant, 'REURB Digital',
            'Requerimento → Análise Jurídica → Análise Urbanística → Titulação', true, [
                ['nome' => 'Requerimento REURB', 'executor' => 'solicitante', 'setor_id' => $s['Protocolo Geral'], 'cor' => '#64748b', 'campos' => [
                    $this->texto('Nome do núcleo urbano', true),
                    $this->checkbox('Modalidade', ['Reurb-S (Interesse Social)', 'Reurb-E (Interesse Específico)'], true),
                ]],
                ['nome' => 'Análise Jurídica', 'executor' => 'analista', 'setor_id' => $s['Procuradoria'], 'cor' => '#8b5cf6', 'sla' => 10, 'campos' => [
                    $this->checkbox('Documentação jurídica', ['Matrícula', 'Comprovante de posse', 'CCU'], false),
                    $this->texto('Parecer jurídico', true),
                ]],
                ['nome' => 'Análise Urbanística', 'executor' => 'analista', 'setor_id' => $s['Obras e Urbanismo'], 'cor' => '#f59e0b', 'sla' => 10, 'campos' => [
                    $this->mapa('Perímetro do núcleo (ponto de referência)', false),
                    $this->texto('Parecer urbanístico', true),
                ]],
                ['nome' => 'Titulação', 'executor' => 'analista', 'setor_id' => $s['Gabinete'], 'cor' => '#16a34a', 'sla' => 5, 'campos' => [
                    $this->texto('Número do título de legitimação', true),
                ]],
            ]);
    }
}
