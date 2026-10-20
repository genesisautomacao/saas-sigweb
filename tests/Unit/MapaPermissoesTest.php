<?php

namespace Tests\Unit;

use App\Support\MapaPermissoes;
use App\Support\Modulos;
use Tests\TestCase;

/**
 * Catálogo do mapa (config/mapa.php) — Release 83. Sem banco: só consistência entre
 * catálogo, módulos, seeder, blade (enableDrawing) e MapDataController (cases).
 */
class MapaPermissoesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        MapaPermissoes::limparCache();
        Modulos::limparCache();
    }

    public function test_toda_permissao_do_catalogo_pertence_ao_modulo_da_entidade(): void
    {
        $erros = [];
        foreach (MapaPermissoes::entidades() as $chave => $def) {
            foreach (['menu', 'mapa', 'criar', 'editar', 'excluir'] as $acao) {
                $perm = $def[$acao] ?? null;
                if (! $perm) {
                    continue;
                }
                $dono = Modulos::daPermissao($perm);
                if ($dono !== ($def['modulo'] ?? null)) {
                    $erros[] = "{$chave}.{$acao} ({$perm}): módulo '{$dono}' ≠ '{$def['modulo']}'";
                }
            }
        }
        $this->assertSame([], $erros, implode("\n", $erros));
    }

    public function test_toda_ferramenta_tem_permissao_com_dono_coerente(): void
    {
        $erros = [];
        foreach (array_keys(MapaPermissoes::ferramentas()) as $id) {
            $perm = MapaPermissoes::permissaoFerramenta($id);
            $donoPerm = Modulos::daPermissao($perm);
            $donoFerramenta = Modulos::daFerramenta($id) ?? Modulos::NUCLEO;
            if ($donoPerm === null) {
                $erros[] = "{$perm} sem módulo no catálogo de módulos";
            } elseif ($donoPerm !== $donoFerramenta) {
                $erros[] = "{$perm}: permissão em '{$donoPerm}', ferramenta '{$id}' em '{$donoFerramenta}'";
            }
        }
        $this->assertSame([], $erros, implode("\n", $erros));
    }

    public function test_toda_permissao_do_catalogo_esta_no_seeder(): void
    {
        $arquivo = file_get_contents(base_path('database/seeders/PermissionsSeeder.php'));
        preg_match_all("/'([a-z_]+)'/", $arquivo, $m);
        $noSeeder = array_merge($m[1], array_map(fn ($id) => 'ferramenta_'.$id, array_keys(MapaPermissoes::ferramentas())));
        $faltam = array_values(array_diff(MapaPermissoes::todasPermissoes(), $noSeeder));
        $this->assertSame([], $faltam, 'Permissões do catálogo do mapa fora do PermissionsSeeder: '.implode(', ', $faltam));
    }

    public function test_camadas_artefatos_e_aliases(): void
    {
        $this->assertSame('rural_localidades', MapaPermissoes::entidadeDaCamada('rural-localidades'));
        $this->assertSame('lotes', MapaPermissoes::entidadeDaCamada('edificacoes'));
        $this->assertSame('perimetros_urbanos', MapaPermissoes::entidadeDaCamada('perimetros'));
        $this->assertSame('patrimonio_publicos', MapaPermissoes::entidadeDaCamada('patrimonio_publico'));
        $this->assertSame('cemiterios', MapaPermissoes::entidadeDaCamada('cemiterios'));
        $this->assertSame('jazigos', MapaPermissoes::entidadeDaCamada('jazigos'));
        $this->assertNull(MapaPermissoes::entidadeDaCamada('camada_que_nao_existe'));

        $this->assertSame('lotes', MapaPermissoes::entidadeDoArtefato('edificacao'));
        $this->assertSame('lotes', MapaPermissoes::entidadeDoArtefato('testada'));
        $this->assertSame('rural_hidrografias', MapaPermissoes::entidadeDoArtefato('rural_hidro_ponto'));
        $this->assertSame('toponimias', MapaPermissoes::entidadeDoArtefato('toponimia'));
        $this->assertNull(MapaPermissoes::entidadeDoArtefato('artefato_inexistente'));

        $this->assertSame('ver_camada_cemiterios', MapaPermissoes::permissao('jazigos', 'mapa'));
        $this->assertSame('create_toponimias', MapaPermissoes::permissao('toponimias', 'criar'));
        $this->assertNull(MapaPermissoes::permissao('coleta', 'criar'));
        $this->assertNull(MapaPermissoes::permissao('mob_fluxos', 'editar'));
    }

    public function test_camadas_publicas_sao_as_do_mapa_do_cidadao(): void
    {
        foreach (['lotes', 'edificacoes', 'quadras', 'bairros', 'zonas', 'mob_vias', 'rural-pontes', 'pontos_panoramicos', 'jazigos', 'setores_pgv', 'setores_fiscais'] as $c) {
            $this->assertTrue(MapaPermissoes::camadaPublica($c), "{$c} deveria ser pública");
        }
        foreach (['coleta', 'processos', 'chamados', 'chamados_mapa', 'areas_reurb', 'secoes_logradouro', 'meio_fios', 'toponimias', 'patrimonio_publicos', 'desconhecida'] as $c) {
            $this->assertFalse(MapaPermissoes::camadaPublica($c), "{$c} NÃO deveria ser pública");
        }
    }

    public function test_todo_artefato_do_blade_esta_no_catalogo(): void
    {
        $blade = file_get_contents(base_path('resources/views/filament/pages/mapa-fullscreen.blade.php'));
        preg_match_all("/enableDrawing\\('([a-z_]+)'/", $blade, $m);
        $semEntidade = [];
        foreach (array_unique($m[1]) as $artefato) {
            if (MapaPermissoes::entidadeDoArtefato($artefato) === null) {
                $semEntidade[] = $artefato;
            }
        }
        $this->assertSame([], $semEntidade, 'Botões de desenho sem entidade no config/mapa.php: '.implode(', ', $semEntidade));
    }

    public function test_toda_camada_do_map_data_controller_esta_no_catalogo(): void
    {
        $php = file_get_contents(base_path('app/Http/Controllers/Api/MapDataController.php'));
        $soGetMapData = substr($php, 0, strpos($php, 'public function searchLote') ?: strlen($php));
        preg_match_all("/^\\s+case '([a-z_-]+)':/m", $soGetMapData, $m);
        $semEntidade = [];
        foreach (array_unique($m[1]) as $camada) {
            if (MapaPermissoes::entidadeDaCamada($camada) === null) {
                $semEntidade[] = $camada;
            }
        }
        $this->assertSame([], $semEntidade, 'Camadas servidas sem entidade no config/mapa.php: '.implode(', ', $semEntidade));
    }

    public function test_sem_usuario_nunca_pode(): void
    {
        $tenant = new \App\Models\Tenant(['modules' => ['base_cartografica', 'imobiliario']]);
        $this->assertFalse(MapaPermissoes::pode('lotes', 'criar', null, $tenant));
        $this->assertFalse(MapaPermissoes::podeFerramenta('medir', null, $tenant));
        $this->assertFalse(MapaPermissoes::podeVerCamada('coleta', null, $tenant));
        // camada pública = qualquer um, desde que o módulo esteja ativo
        $this->assertTrue(MapaPermissoes::podeVerCamada('lotes', null, $tenant));
        $this->assertFalse(MapaPermissoes::podeVerCamada('mob_vias', null, $tenant)); // módulo inativo
    }
}
