<?php

namespace Tests\Feature;

use App\Filament\Pages\MapaFullscreen;
use App\Models\Bairro;
use App\Models\Role;
use App\Models\SetorCadastral;
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
 * B9 (2026-10-19) — rename do setor da PGV + Setor Cadastral da inscrição. Contra o
 * PostGIS local (tenant de testes Santa Cecília); pulado no sqlite do phpunit.xml:
 *
 *   DB_CONNECTION=pgsql DB_DATABASE=saas_sigweb php artisan test --filter SetorCadastralTest
 */
class SetorCadastralTest extends TestCase
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
        SetorCadastral::withoutGlobalScopes()->where('tenant_id', $tenant->id)->update(['deleted_at' => now()]);

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
        $this->assertFalse(Schema::hasColumn('face_quadras', 'setor_cadastral_id'));
        $this->assertTrue(Schema::hasColumn('bairros', 'setor_cadastral_id'));
        $this->assertFalse(Schema::hasColumn('setores_cadastrais', 'pgv_parametro_id'), 'a tabela nova é a da inscrição');
        // 2026-10-20: o setor da inscrição deixou de se chamar "fiscal" — o nome não existe mais
        $this->assertFalse(Schema::hasTable('setores_fiscais'));
        $this->assertFalse(Schema::hasColumn('bairros', 'setor_fiscal_id'));
        $this->assertDatabaseMissing('permissions', ['name' => 'view_setores_fiscais']);

        foreach (['view', 'create', 'edit', 'delete'] as $a) {
            $this->assertDatabaseHas('permissions', ['name' => "{$a}_setores_pgv"]);
            $this->assertDatabaseHas('permissions', ['name' => "{$a}_setores_cadastrais"]);
        }
        $this->assertDatabaseHas('permissions', ['name' => 'ver_camada_setores_pgv']);

        $this->assertSame('pgv', Modulos::daCamada('setores_pgv'));
        $this->assertSame('base_cartografica', Modulos::daCamada('setores_cadastrais'));
        $this->assertSame(
            DB::table('setores_pgv')->where('tenant_id', $this->tenant->id)->whereNull('deleted_at')->count(),
            SetorPgv::query()->where('tenant_id', $this->tenant->id)->count()
        );
    }

    public function test_cria_setor_no_mapa_e_vincula_os_bairros(): void
    {
        $geo = $this->geoEnvolvendoBairro();
        Bairro::withoutGlobalScopes()->whereKey($this->bairro->id)->update(['setor_cadastral_id' => null]);

        Livewire::actingAs($this->manager())->test(MapaFullscreen::class)
            ->call('interceptarDesenho', 'setor_cadastral', $geo)
            ->assertActionMounted('criarSetorCadastral')
            ->setActionData(['name' => 'T01', 'vincular_bairros' => true])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $setor = SetorCadastral::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'T01')->firstOrFail();
        $this->assertGreaterThanOrEqual(1, $setor->sequential_id);
        $this->assertGreaterThan(0, (float) $setor->area_geo);
        $this->assertSame($setor->id, Bairro::withoutGlobalScopes()->find($this->bairro->id)->setor_cadastral_id);
        $this->assertSame('T01', Bairro::withoutGlobalScopes()->find($this->bairro->id)->setorCadastral->name);
    }

    public function test_setor_nao_invade_outro_e_numero_nao_repete(): void
    {
        $geo = $this->geoEnvolvendoBairro();
        SetorCadastral::create(['tenant_id' => $this->tenant->id, 'name' => 'T02', 'geo' => $geo]);

        // mesma área = desenho todo dentro do vizinho → recusado, nem abre o modal (B10)
        Livewire::actingAs($this->manager())->test(MapaFullscreen::class)
            ->call('interceptarDesenho', 'setor_cadastral', $geo)
            ->assertNotified('Desenho recusado')
            ->assertActionNotMounted('criarSetorCadastral');

        // área livre (longe), mas o número já existe → erro de validação
        $longe = json_decode((string) DB::selectOne(
            'SELECT ST_AsGeoJSON(ST_Translate(ST_GeomFromGeoJSON(?), 1, 1)) AS g', [json_encode($geo)]
        )->g, true);
        Livewire::actingAs($this->manager())->test(MapaFullscreen::class)
            ->call('interceptarDesenho', 'setor_cadastral', $longe)
            ->assertActionMounted('criarSetorCadastral')
            ->setActionData(['name' => 'T02'])
            ->callMountedAction()
            ->assertHasActionErrors(['name' => 'unique']);
    }

    /** Quadrado em metros (UTM aproximado via geography) a partir do centro do bairro de teste. */
    private function quadrado(float $dxIni, float $dxFim, float $lado = 400): array
    {
        $c = DB::selectOne('SELECT ST_X(ST_PointOnSurface(geo)) x, ST_Y(ST_PointOnSurface(geo)) y FROM bairros WHERE id = ?', [$this->bairro->id]);
        $m = 1 / 111320; // grau por metro (aprox.; só para montar o teste)
        $x0 = $c->x + $dxIni * $m;
        $x1 = $c->x + $dxFim * $m;
        $y0 = $c->y;
        $y1 = $c->y + $lado * $m;

        return ['type' => 'Polygon', 'coordinates' => [[[$x0, $y0], [$x1, $y0], [$x1, $y1], [$x0, $y1], [$x0, $y0]]]];
    }

    public function test_desenho_que_invade_o_vizinho_e_recortado_na_divisa(): void
    {
        // vizinho: faixa de 0 a 400 m; novo: de 300 a 700 m → invade 100 m × 400 m
        $vizinho = SetorCadastral::create(['tenant_id' => $this->tenant->id, 'name' => 'T10', 'geo' => $this->quadrado(0, 400)]);

        Livewire::actingAs($this->manager())->test(MapaFullscreen::class)
            ->call('interceptarDesenho', 'setor_cadastral', $this->quadrado(300, 700))
            ->assertNotified('Divisa ajustada')
            ->assertActionMounted('criarSetorCadastral')
            ->setActionData(['name' => 'T11', 'vincular_bairros' => false])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $novo = SetorCadastral::where('name', 'T11')->firstOrFail();
        $sobra = (float) DB::selectOne('SELECT ST_Area(ST_Intersection(a.geo, b.geo)::geography) m2 FROM setores_cadastrais a, setores_cadastrais b WHERE a.id = ? AND b.id = ?', [$novo->id, $vizinho->id])->m2;
        $this->assertLessThan(1, $sobra, 'depois do recorte os setores não se sobrepõem');
        $this->assertTrue((bool) DB::selectOne('SELECT ST_Touches(a.geo, b.geo) OR ST_Intersects(a.geo, b.geo) t FROM setores_cadastrais a, setores_cadastrais b WHERE a.id = ? AND b.id = ?', [$novo->id, $vizinho->id])->t, 'a divisa fica colada');

        // edição da geometria do novo invadindo de novo → recortada, não revertida
        Livewire::actingAs($this->manager())->test(MapaFullscreen::class)
            ->call('salvarNovaGeometriaSetorCadastral', $novo->id, $this->quadrado(200, 700))
            ->assertNotified('Limites do setor atualizados!')
            ->assertDispatched('recarregarCamada');
        $sobra = (float) DB::selectOne('SELECT ST_Area(ST_Intersection(a.geo, b.geo)::geography) m2 FROM setores_cadastrais a, setores_cadastrais b WHERE a.id = ? AND b.id = ?', [$novo->id, $vizinho->id])->m2;
        $this->assertLessThan(1, $sobra);

        // engolir o vizinho inteiro = engano → recusado
        Livewire::actingAs($this->manager())->test(MapaFullscreen::class)
            ->call('interceptarDesenho', 'setor_cadastral', $this->quadrado(-100, 1200, 600))
            ->assertNotified('Desenho recusado')
            ->assertActionNotMounted('criarSetorCadastral');
    }

    public function test_papel_sem_permissao_nao_cria_e_camada_e_publica(): void
    {
        $papel = Role::create(['name' => 'B9 Leitor '.uniqid(), 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $papel->syncPermissions(['ver_camada_setores_cadastrais']);
        $u = new User;
        $u->forceFill(['name' => 'B9 leitor', 'email' => 'b9-'.uniqid().'@teste.local', 'password' => 'segredo-b9', 'tipo' => 'prefeitura'])->save();
        $u->tenants()->attach($this->tenant->id);
        $u->assignRole($papel);

        Livewire::actingAs($u->fresh())->test(MapaFullscreen::class)
            ->call('interceptarDesenho', 'setor_cadastral', $this->geoEnvolvendoBairro())
            ->assertNotified('Sem permissão')
            ->assertActionNotMounted('criarSetorCadastral');

        SetorCadastral::create(['tenant_id' => $this->tenant->id, 'name' => 'T03', 'geo' => $this->geoEnvolvendoBairro()]);
        $json = $this->get("/api/gis-data?tenant_id={$this->tenant->id}&layer=setores_cadastrais")->assertOk()->json();
        $this->assertContains('T03', collect($json['features'])->pluck('properties.name')->all());
    }
}
