<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ViabilidadeSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = DB::table('tenants')->first()->id ?? 1;

        $this->importarCnaes($tenantId);
        $this->importarRegras($tenantId);
    }

    private function importarCnaes($tenantId)
    {
        $path = database_path('data/cnaes.json');
        if (!File::exists($path)) {
            $this->command->error("Arquivo cnaes.json não encontrado.");
            return;
        }

        $json = File::get($path);
        $data = json_decode($json, true);

        if (!$data) {
            $this->command->error("Erro ao decodificar JSON de CNAEs.");
            return;
        }

        $insertData = [];
        foreach ($data as $item) {
            // Explode a string "CS3, SC2" -> ["CS3", "SC2"]
            $listaClassificacoes = isset($item['classificacao'])
                ? array_map('trim', explode(',', $item['classificacao']))
                : [];

            $insertData[] = [
                'tenant_id' => $tenantId,
                'codigo' => trim($item['cnae']),
                'descricao' => $item['descricao'],
                'classificacoes' => json_encode($listaClassificacoes),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // LIMPEZA ANTES DE INSERIR
        DB::table('cnaes')->where('tenant_id', $tenantId)->delete();

        foreach (array_chunk($insertData, 500) as $chunk) {
            DB::table('cnaes')->insert($chunk);
        }
        $this->command->info(count($insertData) . ' CNAEs importados.');
    }

    private function importarRegras($tenantId)
    {
        $path = database_path('data/zoneamento_regras.json');
        if (!File::exists($path)) {
            $this->command->error("Arquivo zoneamento_regras.json não encontrado.");
            return;
        }

        $json = File::get($path);
        $data = json_decode($json, true);

        $insertData = [];

        foreach ($data as $row) {
            $zona = trim($row['zonas']);

            $map = [
                'permitido' => $row['permitido'] ?? null,
                'permissivel' => $row['permissivel'] ?? null,
                'proibido' => $row['proibido'] ?? null,
            ];

            foreach ($map as $status => $listaString) {
                if (!$listaString)
                    continue;

                $classificacoes = array_map('trim', explode(',', $listaString));

                foreach ($classificacoes as $classificacao) {
                    if (empty($classificacao))
                        continue;

                    $insertData[] = [
                        'tenant_id' => $tenantId,
                        'zona_sigla' => $zona,
                        'classificacao' => $classificacao,
                        'status' => $status,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        // --- AQUI ESTAVA FALTANDO: LIMPEZA ANTES DE INSERIR ---
        DB::table('zoneamento_regras')->where('tenant_id', $tenantId)->delete();

        DB::table('zoneamento_regras')->insert($insertData);
        $this->command->info(count($insertData) . ' Regras de Zoneamento importadas.');
    }
}