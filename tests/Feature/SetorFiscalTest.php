<?php

namespace Tests\Feature;

use App\Filament\Pages\MapaFullscreen;
use App\Models\Bairro;
use App\Models\Role;
use App\Models\SetorFiscal;
use App\Models\SetorPgv;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MapaPermissoes;
use App\Support\Modulos;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * B9 (2026-10-19) — rename do setor da PGV + Setor Fiscal da inscrição. Contra o
 * PostGIS local (tenant de testes Santa Cecília); pulado no sqlite do phpunit.xml:
 *
 *   DB_CONNECTION=pgsql DB_DATABASE=saas_sigweb php artisan test --filter SetorFiscalTest
 */
class SetorFiscalTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private Bairro $bairro;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Precisa do banco PostGIS local (DB_CONNECTION=pgsql DB_DATABASE=saas_sigweb).');
        }
        $tenant = Tenant::where('slug', 'prefeitura-de-santa-cecilia')->first();
        if (! $tenant || ! Schema::hasTable('setores_pgv')) {
            $this->markTestSkipped('Tenant de testes (Santa Cecília) ou migrations do B9 ausentes.');
        }
        $this->tenant = $tenant;
        // Setores já cadastrados no tenant de testes bloqueariam o desenho (sobreposição) —
        // vão para a lixeira dentro da transação, que é desfeita ao fim de cada teste.
        SetorFiscal::withoutGlobalScopes()->where('tenant_id', $tenant->id)->update(['deleted_at' => now()]);

        $this->bairro = Bairro::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNotNull('geo')->orderBy('id')->firstOrFail();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($tenant, true);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        MapaPermissoes::limparCache();
        Modulos::limparCache();
    }

    private function manager(): User
    {
        return Role::managerDe($this->tenant->id)->users()->first();
    }

    /** Polígono que contém o bairro de teste (bairro + 20 m) — nenhum setor existe ainda. */
    private function geoEnvolvendoBairro(float $metros = 20): array
    {
        return json_decode((string) DB::table('bairros')->where('id', $this->bairro->id)
            ->value(DB::raw("ST_AsGeoJSON(ST_Buffer(geo::geography, {$metros})::geometry)")), true);
    }

    public function test_rename_preservou_o_setor_da_pgv(): void
    {
        $this->assertTrue(Schema::hasColumn('setores_pgv', 'pgv_parametro_id'));
        $this->assertTrue(Schema::hasColumn('face_quadras', 'setor_pgv_id'));
        $this->assertTrue(Schema::hasColumn('lote_valores_historicos', 'setor_pgv_id'));
        $this->assertFalse(Schema::hasColumn('face_quadras', 'setor_fiscal_id'));
        $this->assertTrue(Schema::hasColumn('bairros', 'setor_fiscal_id'));
        $this->assertFalse(Schema::hasColumn('setores_fiscais', 'pgv_parametro_id'), 'a tabela nova é a da inscrição');

        foreach (['view', 'create', 'edit', 'delete'] as $a) {
            $this->assertDatabaseHas('permissions', ['name' => "{$a}_setores_pgv"]);
            $this->assertDatabaseHas('permissions', ['name' => "{$a}_setores_fiscais"]);
        }
        $this->assertDatabaseHas('permissions', ['name' => 'ver_camada_setores_pgv']);

        $this->assertSame('pgv', Modulos::daCamada('setores_pgv'));
        $this->assertSame('base_cartografica', Modulos::daCamada('setores_fiscais'));
        $this->assertSame(
            DB::table('setores_pgv')->where('tenant_id', $this->tenant->id)->whereNull('deleted_at')->count(),
            SetorPgv::query()->where('tenant_id', $this->tenant->id)->count()
        );
    }

    public function test_cria_setor_no_mapa_e_vincula_os_bairros(): void
    {
        $geo = $this->geoEnvolvendoBairro();
        Bairro::withoutGlobalScopes()->whereKey($this->bairro->id)->update(['setor_fiscal_id' => null]);

        Livewire::actingAs($this->manager())->test(MapaFullscreen::class)
            ->call('interceptarDesenho', 'setor_fiscal', $geo)
            ->assertActionMounted('criarSetorFiscal')
            ->setActionData(['name' => 'T01', 'vincular_bairros' => true])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $setor = SetorFiscal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'T01')->firstOrFail();
        $this->assertGreaterThanOrEqual(1, $setor->sequential_id);
        $this->assertGreaterThan(0, (float) $setor->area_geo);
        $this->assertSame($setor->id, Bairro::withoutGlobalScopes()->find($this->bairro->id)->setor_fiscal_id);
        $this->assertSame('T01', Bairro::withoutGlobalScopes()->find($this->bairro->id)->setorFiscal->name);
    }

    public function test_setor_nao_invade_outro_e_numero_nao_repete(): void
    {
        $geo = $this->geoEnvolvendoBairro();
        SetorFiscal::create(['tenant_id' => $this->tenant->id, 'name' => 'T02', 'geo' => $geo]);

        // mesma área = sobreposição → nem abre o modal
        Livewire::actingAs($this->manager())->test(MapaFullscreen::class)
            ->call('interceptarDesenho', 'setor_fiscal', $geo)
            ->assertNotified('Sobreposição detectada')
            ->assertActionNotMounted('criarSetorFiscal');

        // área livre (longe), mas o número já existe → erro de validação
        $longe = json_decode((string) DB::selectOne(
            'SELECT ST_AsGeoJSON(ST_Translate(ST_GeomFromGeoJSON(?), 1, 1)) AS g', [json_encode($geo)]
        )->g, true);
        Livewire::actingAs($this->manager())->test(MapaFullscreen::class)
            ->call('interceptarDesenho', 'setor_fiscal', $longe)
            ->assertActionMounted('criarSetorFiscal')
            ->setActionData(['name' => 'T02'])
            ->callMountedAction()
            ->assertHasActionErrors(['name' => 'unique']);
    }

    public function test_papel_sem_permissao_nao_cria_e_camada_e_publica(): void
    {
        $papel = Role::create(['name' => 'B9 Leitor '.uniqid(), 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $papel->syncPermissions(['ver_camada_setores_fiscais']);
        $u = new User;
        $u->forceFill(['name' => 'B9 leitor', 'email' => 'b9-'.uniqid().'@teste.local', 'password' => 'segredo-b9', 'tipo' => 'prefeitura'])->save();
        $u->tenants()->attach($this->tenant->id);
        $u->assignRole($papel);

        Livewire::actingAs($u->fresh())->test(MapaFullscreen::class)
            ->call('interceptarDesenho', 'setor_fiscal', $this->geoEnvolvendoBairro())
            ->assertNotified('Sem permissão')
            ->assertActionNotMounted('criarSetorFiscal');

        SetorFiscal::create(['tenant_id' => $this->tenant->id, 'name' => 'T03', 'geo' => $this->geoEnvolvendoBairro()]);
        $json = $this->get("/api/gis-data?tenant_id={$this->tenant->id}&layer=setores_fiscais")->assertOk()->json();
        $this->assertContains('T03', collect($json['features'])->pluck('properties.name')->all());
    }
}
