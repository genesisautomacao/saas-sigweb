<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\LeadStatus;
use App\Models\LeadPotential;
use App\Models\LeadSource;

class SeedTenantCrm extends Command
{
    // A assinatura do comando exige o ID do Tenant
    protected $signature = 'tenant:seed-crm {tenant_id}';

    protected $description = 'Popula as tabelas bases do CRM (Status, Potencial, Origem) para um Tenant específico';

    public function handle()
    {
        $tenantId = $this->argument('tenant_id');

        // Verifica se o Tenant existe
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant com ID {$tenantId} não encontrado!");
            return Command::FAILURE;
        }

        $this->info("Iniciando a configuração do CRM para a transportadora: {$tenant->name}");

        $this->seedStatuses($tenantId);
        $this->seedPotentials($tenantId);
        $this->seedSources($tenantId);

        $this->info("✅ CRM configurado com sucesso para a Tenant ID {$tenantId}!");
        return Command::SUCCESS;
    }

    private function seedStatuses($tenantId)
    {
        $this->warn('Semeando Statuses...');

        $statuses = [
            ['name' => 'Pendente', 'color' => '#f59e0b', 'is_default' => true], // Laranjado (Amber-500)
            ['name' => 'Acompanhando', 'color' => '#3b82f6', 'is_default' => false], // Azul
            ['name' => 'Alto Risco de Roubo', 'color' => '#ef4444', 'is_default' => false], // Vermelho
            ['name' => 'Apresentação enviada', 'color' => '#8b5cf6', 'is_default' => false], // Roxo
            ['name' => 'Atendimento todo Brasil', 'color' => null, 'is_default' => false],
            ['name' => 'BID', 'color' => null, 'is_default' => false],
            ['name' => 'Cadastro no site', 'color' => null, 'is_default' => false],
            ['name' => 'CD em outro estado', 'color' => null, 'is_default' => false],
            ['name' => 'Cliente', 'color' => '#10b981', 'is_default' => false], // Verde (Sucesso)
            ['name' => 'Contato via e-mail', 'color' => null, 'is_default' => false],
            ['name' => 'Contato via WhatsApp', 'color' => '#22c55e', 'is_default' => false], // Verde Whats
            ['name' => 'Cotação / Esporadico', 'color' => null, 'is_default' => false],
            ['name' => 'Encerrou as atividades', 'color' => '#6b7280', 'is_default' => false], // Cinza escuro
            ['name' => 'Entrar em contato', 'color' => null, 'is_default' => false],
            ['name' => 'Enviado para vendedor', 'color' => null, 'is_default' => false],
            ['name' => 'Escritório no Brasil', 'color' => null, 'is_default' => false],
            ['name' => 'Ex-cliente', 'color' => '#9ca3af', 'is_default' => false],
            ['name' => 'Ex-cliente / Não entrar em contato', 'color' => '#ef4444', 'is_default' => false],
            ['name' => 'Fechou com outra transportadora', 'color' => '#ef4444', 'is_default' => false],
            ['name' => 'FOB', 'color' => null, 'is_default' => false],
            ['name' => 'Frota própria', 'color' => null, 'is_default' => false],
            ['name' => 'Loja', 'color' => null, 'is_default' => false],
            ['name' => 'Lotação / Carga Fechada', 'color' => null, 'is_default' => false],
            ['name' => 'Mercadoria Fora do Nosso Perfil', 'color' => '#f97316', 'is_default' => false],
            ['name' => 'Motoboy', 'color' => null, 'is_default' => false],
            ['name' => 'Não houve interesse', 'color' => '#6b7280', 'is_default' => false],
            ['name' => 'Não trabalha com transportadora', 'color' => null, 'is_default' => false],
            ['name' => 'Operação Dedicada', 'color' => null, 'is_default' => false],
            ['name' => 'Operação Grande', 'color' => null, 'is_default' => false],
            ['name' => 'Parceria a muitos anos com transportadora', 'color' => null, 'is_default' => false],
            ['name' => 'Pequenas Encomendas / Correios / Courrier', 'color' => null, 'is_default' => false],
            ['name' => 'Pequeno', 'color' => null, 'is_default' => false],
            ['name' => 'Precisa de transportadora atenda todo Brasil', 'color' => null, 'is_default' => false],
            ['name' => 'Preço Baixo', 'color' => null, 'is_default' => false],
            ['name' => 'Prestação de serviço', 'color' => null, 'is_default' => false],
            ['name' => 'Quimico', 'color' => null, 'is_default' => false],
            ['name' => 'Recuperação judical', 'color' => '#ef4444', 'is_default' => false],
            ['name' => 'Retornar a Ligação', 'color' => '#eab308', 'is_default' => false], // Amarelo
            ['name' => 'Reunião', 'color' => '#0ea5e9', 'is_default' => false], // Azul claro
            ['name' => 'RFI', 'color' => null, 'is_default' => false],
            ['name' => 'Tabela enviada', 'color' => null, 'is_default' => false],
            ['name' => 'Telefone errado', 'color' => '#f43f5e', 'is_default' => false],
            ['name' => 'Telefone não localizado na busca do Google', 'color' => null, 'is_default' => false],
            ['name' => 'Usa agregado', 'color' => null, 'is_default' => false],
            ['name' => 'Usa Operador Logístico', 'color' => null, 'is_default' => false],
            ['name' => 'Zona ABC', 'color' => null, 'is_default' => false],
            ['name' => 'Zona Sul', 'color' => null, 'is_default' => false],
        ];

        // Usamos um contador manual para garantir a ordem exata do Array no banco de dados!
        $order = 1;
        foreach ($statuses as $status) {
            LeadStatus::updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $status['name']], // Busca por nome e tenant
                [
                    'color' => $status['color'],
                    'is_default' => $status['is_default'],
                    'order' => $order
                ]
            );
            $order++;
        }
    }

    private function seedPotentials($tenantId)
    {
        $this->warn('Semeando Potenciais...');

        $potentials = [
            ['name' => 'Pendente', 'color' => '#f59e0b', 'is_default' => true],
            ['name' => 'Grande / 300 Entregas mês', 'color' => null, 'is_default' => false],
            ['name' => 'Médio / 200 Entregas mês', 'color' => null, 'is_default' => false],
            ['name' => 'Pequeno / 100 Entregas mês', 'color' => null, 'is_default' => false],
            ['name' => 'Premium / Acima 350 Entregas mês', 'color' => '#8b5cf6', 'is_default' => false], // Roxo Premium
        ];

        foreach ($potentials as $potential) {
            LeadPotential::updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $potential['name']],
                [
                    'color' => $potential['color'],
                    'is_default' => $potential['is_default']
                ]
            );
        }
    }

    private function seedSources($tenantId)
    {
        $this->warn('Semeando Origens...');

        $sources = [
            ['name' => 'Pendente', 'color' => '#f59e0b', 'is_default' => true],
            ['name' => 'Google', 'color' => '#3b82f6', 'is_default' => false], // Azul Google
            ['name' => 'Indicação', 'color' => '#10b981', 'is_default' => false],
            ['name' => 'Listagem / Mailing', 'color' => null, 'is_default' => false],
            ['name' => 'Outros', 'color' => null, 'is_default' => false],
            ['name' => 'Transvias', 'color' => '#f97316', 'is_default' => false], // Laranja Transvias
        ];

        foreach ($sources as $source) {
            LeadSource::updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $source['name']],
                [
                    'color' => $source['color'],
                    'is_default' => $source['is_default']
                ]
            );
        }
    }
}