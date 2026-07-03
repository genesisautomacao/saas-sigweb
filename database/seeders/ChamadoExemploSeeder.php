<?php

namespace Database\Seeders;

use App\Models\CategoriaChamado;
use App\Models\Chamado;
use App\Models\FaseChamado;
use App\Models\FluxoChamado;
use App\Models\MensagemChamado;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ChamadoExemploSeeder extends Seeder
{
    public function run(): void
    {
        $slug = env('CHAMADO_SEED_TENANT', 'prefeitura-de-santa-cecilia');
        $tenant = Tenant::where('slug', $slug)->first();
        if (! $tenant) {
            $this->command->warn("Tenant {$slug} não encontrado.");

            return;
        }
        $tid = $tenant->id;

        if (Chamado::where('tenant_id', $tid)->exists()) {
            $this->command->info("Tenant {$slug} já tem chamados — pulando (idempotente).");

            return;
        }

        $lat = (float) data_get($tenant->data, 'map_lat', -26.9599);
        $lon = (float) data_get($tenant->data, 'map_lon', -50.4268);

        // Categorias (pai/filho + uma privada) — itens 155–159
        $catIlum = CategoriaChamado::create(['tenant_id' => $tid, 'nome' => 'Iluminação Pública', 'cor' => '#f59e0b', 'ordem' => 1]);
        $catLamp = CategoriaChamado::create(['tenant_id' => $tid, 'nome' => 'Lâmpada apagada', 'pai_id' => $catIlum->id, 'cor' => '#f59e0b', 'ordem' => 1]);
        $catVias = CategoriaChamado::create(['tenant_id' => $tid, 'nome' => 'Vias e Calçadas', 'cor' => '#3b82f6', 'ordem' => 2]);
        $catFisc = CategoriaChamado::create(['tenant_id' => $tid, 'nome' => 'Fiscalização (interna)', 'cor' => '#ef4444', 'privada' => true, 'ordem' => 3]);

        // Fluxo + fases + boletim (149–154)
        $fluxo = FluxoChamado::create([
            'tenant_id' => $tid,
            'nome' => 'Atendimento de Chamado',
            'categoria_chamado_id' => $catIlum->id,
            'ativo' => true,
            'boletim' => [
                ['type' => 'texto', 'data' => ['label_campo' => 'Descreva o problema', 'obrigatorio' => true]],
                ['type' => 'checkbox', 'data' => ['label_campo' => 'Período do problema', 'opcoes' => ['Dia', 'Noite', 'Sempre'], 'obrigatorio' => false]],
            ],
        ]);
        $fAberto = FaseChamado::create(['tenant_id' => $tid, 'fluxo_chamado_id' => $fluxo->id, 'nome' => 'Aberto', 'cor' => '#3b82f6', 'ordem' => 1]);
        $fAnalise = FaseChamado::create(['tenant_id' => $tid, 'fluxo_chamado_id' => $fluxo->id, 'nome' => 'Em análise', 'cor' => '#f59e0b', 'ordem' => 2, 'duracao_minutos' => 2880, 'aviso_duracao' => true]);
        $fResolvido = FaseChamado::create(['tenant_id' => $tid, 'fluxo_chamado_id' => $fluxo->id, 'nome' => 'Resolvido', 'cor' => '#22c55e', 'ordem' => 3, 'encerramento' => true]);

        // Chamados de exemplo com geometria (160–174)
        $exemplos = [
            ['Poste apagado na Rua Central', $catLamp->id, $fAberto->id, 0.0010, 0.0010, ['Descreva o problema' => 'Poste apagado há 3 dias', 'Período do problema' => ['Noite']]],
            ['Buraco na calçada em frente à escola', $catVias->id, $fAnalise->id, -0.0012, 0.0008, ['Descreva o problema' => 'Calçada quebrada, risco de queda']],
            ['Lâmpada piscando à noite', $catLamp->id, $fResolvido->id, 0.0015, -0.0010, ['Descreva o problema' => 'Lâmpada piscando', 'Período do problema' => ['Noite', 'Sempre']]],
            ['Fiscalização de obra irregular', $catFisc->id, $fAberto->id, -0.0008, -0.0011, null],
        ];

        foreach ($exemplos as [$desc, $catId, $faseId, $dlat, $dlon, $resp]) {
            $ch = Chamado::create([
                'tenant_id' => $tid,
                'categoria_chamado_id' => $catId,
                'fluxo_chamado_id' => $fluxo->id,
                'fase_atual_id' => $faseId,
                'solicitante_nome' => 'Munícipe Exemplo',
                'solicitante_telefone' => '(49) 99999-0000',
                'descricao' => $desc,
                'respostas_boletim' => $resp,
                'status' => 'aberto',
                'geo' => ['type' => 'Point', 'coordinates' => [$lon + $dlon, $lat + $dlat]],
            ]);

            MensagemChamado::create(['tenant_id' => $tid, 'chamado_id' => $ch->id, 'user_id' => null, 'texto' => 'Recebemos seu chamado, obrigado. Em breve retornaremos.', 'publica' => true]);
            MensagemChamado::create(['tenant_id' => $tid, 'chamado_id' => $ch->id, 'user_id' => null, 'texto' => 'Nota interna: verificar disponibilidade de equipe.', 'publica' => false]);
        }

        $this->command->info("ChamadoExemploSeeder: 4 categorias, 1 fluxo (3 fases), 4 chamados no tenant {$slug}.");
    }
}
