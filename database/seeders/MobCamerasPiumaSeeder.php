<?php

namespace Database\Seeders;

use App\Models\MobCamera;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * As 7 câmeras municipais de Piúma/ES (IZ1 Telecom, player FullCam) publicadas
 * no site da prefeitura — demo do "Monitoramento em tempo Real"
 * (docs/piuma.txt, Onda 5). Embeds públicos; nada é gravado pelo SIGWEB.
 *
 * Posições aproximadas (POIs do levantamento + orla + entroncamento ES-060/
 * ES-375); a prefeitura ajusta no mapa com "Reposicionar". Idempotente por
 * (tenant, nome): re-rodar atualiza URL/posição.
 *
 *   MOB_SEED_TENANT=<slug> php artisan db:seed --class=MobCamerasPiumaSeeder
 */
class MobCamerasPiumaSeeder extends Seeder
{
    private const EMBED = 'https://cloud.fullcam.me/#/cembed/';

    /** [nome, token FullCam, lat, lon, azimute da visada, descrição] */
    public const CAMERAS = [
        ['Praça Dona Carmem (Centro)', '324a1c609aa193faa849ea5892e35f66de1b44449c66f833eb50a8a37d26f93ec5abdc65078edd26c706b7d4c262', -20.845868, -40.741583, 90, 'Praça no centro de Piúma.'],
        ['Praia - Visão 3 Ilhas', '747836cbd2b9d5a8205c6ed7ab60aab86cdb8a2b5c8744c0f62a9a1ec1a3c3b5d98521b4c5982666dd33a67b3016', -20.843050, -40.733900, 120, 'Orla central, vista panorâmica das ilhas.'],
        ['Praia - Visão Monte Aghá', '6313ac207b491b60f9c24ac01573670d448457ad332c3b035ea8842f7b5db200cd41dcc9907cb02f5de7aa07f236', -20.843350, -40.735200, 225, 'Orla central, vista do Monte Aghá.'],
        ['Campo - Ilha do Gambá', '159155ba8939ad23bec36f165613439baf5392385aff3796e0f4d37452d28d6ef6841d7e2ecca388ee65869502b7', -20.843700, -40.722710, 45, 'Campo de futebol da Ilha do Gambá.'],
        ['Maria Neném - Lado Ilha', 'fa77bfb599dc49d610db547ec4515fa6016b7a4719e98044fc1cab40c26d6eb9aec2569d1aeee5826afa78eefd33', -20.855600, -40.752300, 60, 'Praia Maria Neném, visada para a ilha.'],
        ['Maria Neném - Lado Monte Aghá', 'fb109b5d83ec1bf0db698ea0f304c8337d42a0c1dd807618e67f6bc49e497cb3beca3eff0dce4c1464c149a007a5', -20.857900, -40.754800, 235, 'Praia Maria Neném, visada para o Monte Aghá.'],
        ['Trevo de Niterói (Entrada)', 'e4bf9c2c6518d0f7cdc6d3573812b0f5db414875451932dce951ae0abe581d0900b0c9cff1db3083c4e8d8af2e97', -20.834766, -40.723301, 180, 'Entrada de Piúma: entroncamento ES-060 / ES-375.'],
    ];

    public function run(): void
    {
        $tenant = $this->resolverTenant();
        if (! $tenant) {
            return;
        }
        $slug = $tenant->slug;

        $novas = 0;
        foreach (self::CAMERAS as [$nome, $token, $lat, $lon, $azimute, $descricao]) {
            $dados = [
                'tipo' => 'embed',
                'url' => self::EMBED.$token,
                'provedor' => 'IZ1 Telecom (FullCam)',
                'azimute_visada' => $azimute,
                'descricao' => $descricao,
                'ativo' => true,
                'geo' => ['type' => 'Point', 'coordinates' => [$lon, $lat]],
            ];

            $existente = MobCamera::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('nome', $nome)
                ->first();

            if ($existente) {
                $existente->update($dados);
            } else {
                MobCamera::create(array_merge($dados, ['tenant_id' => $tenant->id, 'nome' => $nome]));
                $novas++;
            }
        }

        $this->command?->info(count(self::CAMERAS)." câmeras de Piúma semeadas em '{$slug}' ({$novas} novas).");
    }

    /**
     * MOB_SEED_TENANT=<slug> manda; sem ela, tenta o slug local padrão e, por
     * último, qualquer tenant com "piuma" no slug (o da VPS é
     * 'prefeitura-municipal-de-piuma'). Ambíguo ou ausente = avisa e não grava.
     */
    private function resolverTenant(): ?Tenant
    {
        $slug = env('MOB_SEED_TENANT');
        if ($slug) {
            $tenant = Tenant::where('slug', $slug)->first();
            if (! $tenant) {
                $this->command?->error("Tenant '{$slug}' (MOB_SEED_TENANT) não encontrado - nada semeado.");
            }

            return $tenant;
        }

        $tenant = Tenant::where('slug', 'prefeitura-de-piuma')->first();
        if ($tenant) {
            return $tenant;
        }

        $candidatos = Tenant::where('slug', 'ILIKE', '%piuma%')->get();
        if ($candidatos->count() === 1) {
            $this->command?->info("Slug padrão ausente; usando o tenant '{$candidatos->first()->slug}'.");

            return $candidatos->first();
        }

        $lista = $candidatos->count()
            ? $candidatos->pluck('slug')->implode(', ')
            : Tenant::orderBy('slug')->pluck('slug')->implode(', ');
        $this->command?->error('Não foi possível identificar o tenant de Piúma. Rode com MOB_SEED_TENANT=<slug>. Slugs: '.$lista);

        return null;
    }
}
