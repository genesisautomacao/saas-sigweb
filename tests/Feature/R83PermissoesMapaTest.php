<?php

namespace Tests\Feature;

use App\Filament\Pages\MapaFullscreen;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MapaPermissoes;
use App\Support\Modulos;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Release 83 — verificação FUNCIONAL das permissões do mapa contra o banco local
 * (PostGIS, tenant de testes Santa Cecília). O phpunit.xml aponta para sqlite em
 * memória, então estes testes só rodam com o banco real e são PULADOS fora dele:
 *
 *   DB_CONNECTION=pgsql DB_DATABASE=saas_sigweb php artisan test --filter R83PermissoesMapaTest
 *
 * Papéis/usuários criados aqui são desfeitos por transação ao final de cada teste.
 */
class R83PermissoesMapaTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $leitor;

    private User $editor;

    private Role $papelLeitor;

    private int $loteId;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Precisa do banco PostGIS local (DB_CONNECTION=pgsql DB_DATABASE=saas_sigweb).');
        }
        $tenant = Tenant::where('slug', 'prefeitura-de-santa-cecilia')->first();
        if (! $tenant || DB::table('permissions')->where('name', 'ferramenta_medir')->doesntExist()) {
            $this->markTestSkipped('Tenant de testes (Santa Cecília) ou migration da R83 ausentes.');
        }
        $this->tenant = $tenant;
        $this->loteId = (int) DB::table('lotes')->where('tenant_id', $tenant->id)->whereNotNull('geo')->whereNull('deleted_at')->orderBy('id')->value('id');

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($tenant, true);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        MapaPermissoes::limparCache();
        Modulos::limparCache();

        $this->papelLeitor = $this->papel('R83 Leitor', ['ver_camada_lotes', 'ver_camada_quadras', 'ferramenta_medir']);
        $this->leitor = $this->usuario('leitor', $this->papelLeitor);
        $papelEditor = $this->papel('R83 Editor', [
            'ver_camada_lotes', 'ver_camada_quadras', 'create_lotes', 'edit_lotes',
            'ferramenta_numeracao', 'ferramenta_desmembrar',
        ]);
        $this->editor = $this->usuario('editor', $papelEditor);
    }

    private function papel(string $nome, array $perms): Role
    {
        $role = Role::create(['name' => $nome.' '.uniqid(), 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $role->syncPermissions($perms);

        return $role;
    }

    private function usuario(string $slug, Role $papel): User
    {
        $u = new User;
        $u->forceFill([
            'name' => "R83 {$slug}",
            'email' => "r83-{$slug}-".uniqid().'@teste.local',
            'password' => 'segredo-r83',
            'tipo' => 'prefeitura',
        ])->save();
        $u->tenants()->attach($this->tenant->id);
        $u->assignRole($papel);

        return $u->fresh();
    }

    private function manager(): User
    {
        return Role::managerDe($this->tenant->id)->users()->first();
    }

    private function geoJsonDoLote(): array
    {
        return json_decode((string) DB::table('lotes')->where('id', $this->loteId)->value(DB::raw('ST_AsGeoJSON(geo::geometry)')), true);
    }

    /** O mesmo lote encolhido 0,5 m: não sobrepõe nenhum vizinho (a trava de sobreposição não dispara). */
    private function geoJsonDoLoteEncolhido(): array
    {
        return json_decode((string) DB::table('lotes')->where('id', $this->loteId)
            ->value(DB::raw('ST_AsGeoJSON(ST_Buffer(geo::geography, -0.5)::geometry)')), true);
    }

    public function test_resumo_reflete_o_papel_e_o_bypass(): void
    {
        $r = MapaPermissoes::resumo($this->leitor, $this->tenant);
        $this->assertFalse($r['bypass']);
        $this->assertSame(['mapa' => true, 'criar' => false, 'editar' => false, 'excluir' => false], $r['entidades']['lotes']);
        $this->assertTrue($r['camadas']['edificacoes'], 'edificações seguem o lote');
        $this->assertFalse($r['camadas']['coleta']);
        $this->assertFalse($r['artefatos']['lote']);
        $this->assertFalse($r['artefatos']['edificacao']);
        $this->assertTrue($r['ferramentas']['medir']);
        $this->assertFalse($r['ferramentas']['numeracao']);
        $this->assertFalse($r['ferramentas']['enquadramento']);

        MapaPermissoes::limparCache();
        $e = MapaPermissoes::resumo($this->editor, $this->tenant);
        $this->assertSame(['mapa' => true, 'criar' => true, 'editar' => true, 'excluir' => false], $e['entidades']['lotes']);
        $this->assertTrue($e['artefatos']['edificacao'], 'criar edificação = editar o lote');
        $this->assertTrue($e['artefatos']['testada']);
        $this->assertTrue($e['ferramentas']['numeracao'], 'tem a ferramenta E edita lotes');
        $this->assertTrue($e['ferramentas']['desmembrar']);
        $this->assertFalse($e['ferramentas']['medir']);
        $this->assertFalse($e['entidades']['quadras']['editar']);

        MapaPermissoes::limparCache();
        $m = MapaPermissoes::resumo($this->manager(), $this->tenant);
        $this->assertTrue($m['bypass']);
        $this->assertTrue($m['entidades']['lotes']['excluir']);
        $this->assertTrue($m['ferramentas']['enquadramento']);
        // módulo manda mesmo para o Manager: chamados_mapa está ligado em Santa Cecília, mas uma chave inexistente não
        $this->assertArrayNotHasKey('inexistente', $m['entidades']);
    }

    public function test_dados_das_camadas_respeitam_publico_e_permissao(): void
    {
        $t = $this->tenant->id;

        // ANÔNIMO primeiro (actingAs fica valendo para as chamadas seguintes do mesmo teste):
        // pública = qualquer um; não pública = 403
        $this->get("/api/gis-data?tenant_id={$t}&layer=quadras")->assertOk();
        $this->get("/api/gis-data?tenant_id={$t}&layer=coleta")->assertForbidden();
        // consulta avançada: edificações é pública (mapa do cidadão); seções não
        $this->get("/api/mapa/advanced-query?tenant_id={$t}&layer=edificacoes&field=lote_id&operator=%3D&value={$this->loteId}")->assertOk();
        $this->get("/api/mapa/advanced-query?tenant_id={$t}&layer=secoes_logradouro&field=lado&operator=%3D&value=par")->assertForbidden();

        // EQUIPE sem a permissão da camada = 403; com = 200
        $this->actingAs($this->leitor)->get("/api/gis-data?tenant_id={$t}&layer=coleta")->assertForbidden();
        $this->actingAs($this->leitor)->get("/api/gis-data?tenant_id={$t}&layer=quadras")->assertOk();
        // export SHP: sem a ferramenta = 403 antes de qualquer processamento
        $this->actingAs($this->leitor)->get("/api/mapa/export-shp?tenant_id={$t}&layer=quadras")->assertForbidden();
        $this->actingAs($this->editor)->get("/api/gis-data?tenant_id={$t}&layer=processos")->assertForbidden();
        // Manager = bypass
        $this->actingAs($this->manager())->get("/api/gis-data?tenant_id={$t}&layer=coleta")->assertOk();

        // endpoint do engine
        $json = $this->actingAs($this->leitor)->get("/gis/{$this->tenant->slug}/map-permissions")->assertOk()->json();
        $this->assertFalse($json['bypass']);
        $this->assertContains('lotes', $json['layers']);
        $this->assertContains('edificacoes', $json['layers']);
        $this->assertNotContains('bairros', $json['layers']);
        $this->assertTrue($json['ferramentas']['medir']);
        $this->assertFalse($json['ferramentas']['imprimir']);
        $this->assertFalse($json['permissoes']['lotes']['criar']);
        $this->assertFalse($json['artefatos']['lote']);

        $jsonManager = $this->actingAs($this->manager())->get("/gis/{$this->tenant->slug}/map-permissions")->assertOk()->json();
        $this->assertTrue($jsonManager['bypass']);
        $this->assertNull($jsonManager['layers']);
    }

    public function test_leitor_so_visualiza_no_mapa(): void
    {
        $geoAntes = DB::table('lotes')->where('id', $this->loteId)->value(DB::raw('ST_AsText(geo::geometry)'));
        $geo = $this->geoJsonDoLote();

        $c = Livewire::actingAs($this->leitor)->test(MapaFullscreen::class);
        $c->call('carregarFicha', $this->loteId)->assertSet('showFicha', true);
        $c->call('habilitarEdicaoGeometria')->assertNotified('Sem permissão');
        $c->call('salvarNovaGeometria', $this->loteId, $geo)->assertNotified('Sem permissão');
        $this->assertSame($geoAntes, DB::table('lotes')->where('id', $this->loteId)->value(DB::raw('ST_AsText(geo::geometry)')));

        Livewire::actingAs($this->leitor)->test(MapaFullscreen::class)
            ->call('interceptarDesenho', 'lote', $geo)
            ->assertNotified('Sem permissão')
            ->assertActionNotMounted('criarLote');

        Livewire::actingAs($this->leitor)->test(MapaFullscreen::class)
            ->mountAction('deletarArtefato')->assertActionNotMounted('deletarArtefato');
        Livewire::actingAs($this->leitor)->test(MapaFullscreen::class)
            ->mountAction('editarDadosLote')->assertActionNotMounted('editarDadosLote');
        Livewire::actingAs($this->leitor)->test(MapaFullscreen::class)
            ->mountAction('filtroAvancado')->assertActionNotMounted('filtroAvancado');

        $dataAntes = $this->tenant->fresh()->data;
        Livewire::actingAs($this->leitor)->test(MapaFullscreen::class)
            ->call('salvarEnquadramento', -27.0, -50.0, 15)->assertNotified('Sem permissão');
        $this->assertSame($dataAntes, $this->tenant->fresh()->data);

        Livewire::actingAs($this->leitor)->test(MapaFullscreen::class)
            ->call('confirmarNumeracaoAction')->assertNotified('Sem permissão');
    }

    public function test_editor_cria_e_edita_mas_nao_exclui(): void
    {
        $geo = $this->geoJsonDoLote();

        Livewire::actingAs($this->editor)->test(MapaFullscreen::class)
            ->mountAction('editarDadosLote')->assertActionMounted('editarDadosLote');
        Livewire::actingAs($this->editor)->test(MapaFullscreen::class)
            ->mountAction('deletarArtefato')->assertActionNotMounted('deletarArtefato');
        // desenhar um LOTE sobre o lote existente cai na trava de sobreposição ("Conflito Espacial"),
        // então o editor desenha uma EDIFICAÇÃO (centróide dentro do lote) — criar edificação = editar o lote
        Livewire::actingAs($this->editor)->test(MapaFullscreen::class)
            ->call('interceptarDesenho', 'edificacao', $geo)
            ->assertNotNotified('Sem permissão')
            ->assertActionMounted('criarEdificacao');
        Livewire::actingAs($this->leitor)->test(MapaFullscreen::class)
            ->call('interceptarDesenho', 'edificacao', $geo)
            ->assertNotified('Sem permissão')
            ->assertActionNotMounted('criarEdificacao');
        $geoAntes = DB::table('lotes')->where('id', $this->loteId)->value(DB::raw('ST_AsText(geo::geometry)'));
        // (assertNotified/assertNotNotified CONSOMEM a sessão de notificações — um só por chamada)
        Livewire::actingAs($this->editor)->test(MapaFullscreen::class)
            ->call('salvarNovaGeometria', $this->loteId, $this->geoJsonDoLoteEncolhido())
            ->assertNotified('Geometria Atualizada!');
        $this->assertNotSame($geoAntes, DB::table('lotes')->where('id', $this->loteId)->value(DB::raw('ST_AsText(geo::geometry)')), 'o editor grava geometria');
        // quadras: só vê — a modal de opções abre (ficha), mas o botão de geometria não existe
        Livewire::actingAs($this->editor)->test(MapaFullscreen::class)
            ->call('interceptarDesenho', 'quadra', $geo)
            ->assertNotified('Sem permissão')
            ->assertActionNotMounted('criarQuadra');
    }

    public function test_tela_de_papeis_em_matriz_le_e_grava(): void
    {
        $c = Livewire::actingAs($this->manager())->test(EditRole::class, ['record' => $this->papelLeitor->getRouteKey()])
            ->assertOk()
            ->assertFormSet(function (array $state): void {
                $this->assertEqualsCanonicalizing(['ver_camada_lotes', 'ver_camada_quadras'], $state['permissions_imobiliario']);
                $this->assertEqualsCanonicalizing(['ferramenta_medir'], $state['permissions_mapa_ferramentas']);
            });

        $c->fillForm([
            'permissions_imobiliario' => ['ver_camada_lotes', 'create_lotes', 'edit_lotes'],
            'permissions_mapa_ferramentas' => ['ferramenta_medir', 'ferramenta_imprimir'],
        ])->call('save')->assertHasNoFormErrors();

        $perms = $this->papelLeitor->fresh()->permissions->pluck('name')->all();
        $this->assertContains('create_lotes', $perms);
        $this->assertContains('edit_lotes', $perms);
        $this->assertContains('ferramenta_imprimir', $perms);
        $this->assertNotContains('ver_camada_quadras', $perms, 'desmarcar na matriz remove a permissão');
    }
}
