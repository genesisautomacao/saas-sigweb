<?php

namespace App\Services\ApiTools;

use App\Models\Tenant;
use App\Models\Pessoa;
use App\Models\CadastroSocial;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EsusSyncService
{
    /**
     * Executa a sincronização para uma prefeitura específica
     */
    public function syncTenant(Tenant $tenant)
    {
        $dadosTenant = $tenant->data ?? [];
        $isSimulacao = $dadosTenant['esus_simulacao'] ?? false;
        $url = $dadosTenant['esus_url'] ?? null;
        $token = $dadosTenant['esus_token'] ?? null;

        // Se não for simulação e não tiver credenciais, aborta
        if (!$isSimulacao && (!$url || !$token)) {
            Log::warning("Sincronização e-SUS abortada para Tenant ID {$tenant->id}: Credenciais ausentes.");
            return false;
        }

        try {
            Log::info("Iniciando ETL e-SUS para Tenant ID {$tenant->id}. Modo Simulação: " . ($isSimulacao ? 'ON' : 'OFF'));

            // 1. EXTRAÇÃO (Extract)
            $dadosBrutos = $isSimulacao ? $this->getSimulatedPayload($tenant) : $this->fetchFromApi($url, $token);

            if (empty($dadosBrutos)) {
                return false;
            }

            // 2 & 3. TRANSFORMAÇÃO E CARGA (Transform & Load)
            $this->processPayload($tenant, $dadosBrutos);

            return true;

        } catch (\Exception $e) {
            Log::error("Erro no ETL e-SUS (Tenant {$tenant->id}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Motor principal de Carga e Atualização
     */
    private function processPayload(Tenant $tenant, array $familias)
    {
        foreach ($familias as $familiaData) {
            DB::beginTransaction();
            try {
                // 1. Processa o Responsável Familiar (Pessoa)
                $responsavel = $this->upsertPessoa($tenant, $familiaData['responsavel']);

                // 2. Processa o Cadastro da Família (CadastroSocial)
                $cadastroSocial = $this->upsertFamilia($tenant, $responsavel, $familiaData);

                // 3. Processa Condições de Saúde do Responsável
                if (isset($familiaData['responsavel']['condicoes_saude'])) {
                    $this->upsertCondicoesSaude($tenant, $responsavel, $familiaData['responsavel']['condicoes_saude']);
                }

                // 4. Processa os demais membros da família (se houver)
                if (isset($familiaData['membros']) && is_array($familiaData['membros'])) {
                    foreach ($familiaData['membros'] as $membroData) {
                        $membro = $this->upsertPessoa($tenant, $membroData);
                        
                        if (isset($membroData['condicoes_saude'])) {
                            $this->upsertCondicoesSaude($tenant, $membro, $membroData['condicoes_saude']);
                        }
                    }
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Falha ao salvar família {$familiaData['esus_familia_id']} no Tenant {$tenant->id}: " . $e->getMessage());
            }
        }
    }

    private function upsertPessoa(Tenant $tenant, array $dadosPessoa)
    {
        // Tenta achar pelo CNS primeiro (Chave Mestra da Saúde), depois pelo CPF
        $pessoa = Pessoa::where('tenant_id', $tenant->id)
            ->where(function ($query) use ($dadosPessoa) {
                if (!empty($dadosPessoa['cns'])) {
                    $query->where('cns', $dadosPessoa['cns']);
                }
                if (!empty($dadosPessoa['cpf'])) {
                    $query->orWhere('cpf', $dadosPessoa['cpf']);
                }
            })->first();

        if (!$pessoa) {
            $pessoa = new Pessoa();
            $pessoa->tenant_id = $tenant->id;
            $pessoa->type = 'fisica';
        }

        // Atualiza os dados
        $pessoa->name = $dadosPessoa['nome'];
        $pessoa->cpf = $dadosPessoa['cpf'] ?? $pessoa->cpf;
        $pessoa->cns = $dadosPessoa['cns'] ?? $pessoa->cns;
        $pessoa->esus_id = $dadosPessoa['esus_id'] ?? $pessoa->esus_id;
        $pessoa->birth_date = isset($dadosPessoa['data_nascimento']) ? Carbon::parse($dadosPessoa['data_nascimento']) : $pessoa->birth_date;
        $pessoa->save();

        return $pessoa;
    }

    private function upsertFamilia(Tenant $tenant, Pessoa $responsavel, array $dadosFamilia)
    {
        // Tenta achar pelo ID único da família no e-SUS
        $cadastro = CadastroSocial::where('tenant_id', $tenant->id)
            ->where('esus_familia_id', $dadosFamilia['esus_familia_id'])
            ->first();

        if (!$cadastro) {
            $cadastro = new CadastroSocial();
            $cadastro->tenant_id = $tenant->id;
            $cadastro->esus_familia_id = $dadosFamilia['esus_familia_id'];
        }

        $cadastro->pessoa_id = $responsavel->id;
        $cadastro->nis = $dadosFamilia['nis'] ?? $cadastro->nis;
        $cadastro->quantidade_membros = $dadosFamilia['quantidade_membros'] ?? 1;
        $cadastro->renda_familiar_total = $dadosFamilia['renda_familiar'] ?? null;
        $cadastro->em_area_de_risco = $dadosFamilia['area_de_risco'] ?? false;
        
        $cadastro->save();

        return $cadastro;
    }

    private function upsertCondicoesSaude(Tenant $tenant, Pessoa $pessoa, array $condicoes)
    {
        // Usamos UpdateOrInsert nativo do Laravel para tabela auxiliar
        DB::table('pessoa_saude_condicoes')->updateOrInsert(
            [
                'tenant_id' => $tenant->id,
                'pessoa_id' => $pessoa->id,
            ],
            [
                'is_hipertenso' => $condicoes['hipertenso'] ?? false,
                'is_diabetico' => $condicoes['diabetico'] ?? false,
                'is_gestante' => $condicoes['gestante'] ?? false,
                'is_fumante' => $condicoes['fumante'] ?? false,
                'is_pcd' => $condicoes['pcd'] ?? false,
                'is_acamado' => $condicoes['acamado'] ?? false,
                'ultima_sincronizacao' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Consumo da API Real
     */
    private function fetchFromApi($url, $token)
    {
        $response = Http::withToken($token)
            ->timeout(60)
            ->get($url . '/familias/atualizadas-hoje'); // Exemplo de endpoint padrão e-SUS

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception("Erro na API do e-SUS: " . $response->status());
    }

    /**
     * 🟢 O SEU BANCO DE DADOS EXTERNO FALSO (MOCK API)
     * Coloque aqui os números de CNS que você vai usar na demonstração!
     */
    private function getMockDatabaseEsus()
    {
        return [
            // CNS DO JOÃO (Você digita esse CNS lá no Filament)
            '700111122223333' => [
                'hipertenso' => true,
                'diabetico' => false,
                'gestante' => false,
                'fumante' => true,
                'pcd' => false,
                'acamado' => false
            ],
            // CNS DA MARIA (Você digita esse CNS lá no Filament)
            '700999988887777' => [
                'hipertenso' => false,
                'diabetico' => true,
                'gestante' => true,
                'fumante' => true,
                'pcd' => false,
                'acamado' => false
            ],
            // CNS DO VOVÔ
            '700555544443333' => [
                'hipertenso' => true,
                'diabetico' => true,
                'gestante' => false,
                'fumante' => false,
                'pcd' => false,
                'acamado' => true
            ]
        ];
    }

    /**
     * 🟢 MODO SIMULAÇÃO INTELIGENTE (BASEADO EM BUSCA POR CNS)
     */
    private function getSimulatedPayload(Tenant $tenant)
    {
        sleep(2); // Finge que a internet demorou 2 segundos

        // 1. Pega todas as pessoas físicas do seu banco que tenham o CNS preenchido
        $pessoasComCns = Pessoa::where('tenant_id', $tenant->id)
            ->where('type', 'fisica')
            ->whereNotNull('cns')
            ->get();

        if ($pessoasComCns->isEmpty()) {
            return [];
        }

        $bancoFalso = $this->getMockDatabaseEsus();
        $familiasSimuladas = [];

        // 2. O ETL "pesquisa" cada pessoa sua lá no banco falso do e-SUS
        foreach ($pessoasComCns as $pessoa) {
            
            // Se o CNS da pessoa existir no nosso Banco Falso, a mágica acontece
            if (array_key_exists($pessoa->cns, $bancoFalso)) {
                
                $condicoes = $bancoFalso[$pessoa->cns];

                // Monta o pacote simulando a resposta da API oficial
                $familiasSimuladas[] = [
                    "esus_familia_id" => "FAM-SIMULADA-" . $pessoa->id,
                    "nis" => "12345678901",
                    "quantidade_membros" => 1,
                    "renda_familiar" => 1500.00,
                    "area_de_risco" => false,
                    "responsavel" => [
                        "esus_id" => "CIDAD-" . $pessoa->id,
                        "nome" => $pessoa->name,
                        "cpf" => $pessoa->cpf,
                        "cns" => $pessoa->cns,
                        "condicoes_saude" => $condicoes // 👈 Injeta as doenças encontradas!
                    ],
                    "membros" => []
                ];
            }
        }

        return $familiasSimuladas;
    }
}