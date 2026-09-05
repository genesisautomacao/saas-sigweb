<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Support\Modulos;
use Tests\TestCase;

/**
 * Catálogo de módulos (config/modulos.php) — docs/Modulos_Permissoes.txt.
 * Sem banco: o Tenant é montado em memória só com `modules`.
 */
class ModulosTest extends TestCase
{
    private function tenant(array $modules): Tenant
    {
        return new Tenant(['modules' => $modules]);
    }

    public function test_nucleo_sempre_ativo_e_chaves_nao_incluem_nucleo(): void
    {
        $this->assertTrue(Modulos::ativo('nucleo', $this->tenant([])));
        $this->assertNotContains('nucleo', Modulos::chaves());
        $this->assertContains('base_cartografica', Modulos::chaves());
        $this->assertContains('coleta_cadastral', Modulos::chaves());
        $this->assertContains('imageamento', Modulos::chaves());
        $this->assertContains('chamados', Modulos::chaves()); // D8 (2026-09-05)
    }

    public function test_ativos_ignora_chaves_desconhecidas(): void
    {
        $t = $this->tenant(['mob_infra', 'inexistente', 'base_cartografica']);
        $this->assertEqualsCanonicalizing(['mob_infra', 'base_cartografica'], Modulos::ativos($t));
        $this->assertTrue(Modulos::ativo('mob_infra', $t));
        $this->assertFalse(Modulos::ativo('imobiliario', $t));
    }

    public function test_permissao_pertence_ao_modulo_certo(): void
    {
        $this->assertSame('nucleo', Modulos::daPermissao('view_users'));
        $this->assertSame('nucleo', Modulos::daPermissao('gerenciar_wms'));
        $this->assertSame('base_cartografica', Modulos::daPermissao('view_bairros'));
        $this->assertSame('imobiliario', Modulos::daPermissao('view_lotes'));
        $this->assertSame('imobiliario', Modulos::daPermissao('view_viabilidade_emissoes'));
        $this->assertSame('chamados', Modulos::daPermissao('gerenciar_chamados')); // D8: módulo próprio
        $this->assertSame('chamados', Modulos::daPermissao('ver_camada_chamados'));
        $this->assertSame('coleta_cadastral', Modulos::daPermissao('view_produtividade'));
        $this->assertSame('base_cartografica', Modulos::daPermissao('gerenciar_secoes_logradouro')); // D8: a seção segue o logradouro
        $this->assertSame('base_cartografica', Modulos::daPermissao('ver_camada_secoes_logradouro'));
        $this->assertSame('imageamento', Modulos::daPermissao('ver_camada_pontos_panoramicos'));
        $this->assertSame('mob_infra', Modulos::daPermissao('gerenciar_mob_vias'));
        $this->assertNull(Modulos::daPermissao('permissao_que_nao_existe'));
    }

    public function test_permissao_desconhecida_nunca_e_escondida(): void
    {
        $t = $this->tenant(['mob_infra']);
        $this->assertTrue(Modulos::permissaoDisponivel('permissao_que_nao_existe', $t));
        $this->assertTrue(Modulos::permissaoDisponivel('view_users', $t));
        $this->assertFalse(Modulos::permissaoDisponivel('view_lotes', $t));
    }

    public function test_filtrar_opcoes_piuma_so_mobilidade_e_base(): void
    {
        $t = $this->tenant(['base_cartografica', 'mob_infra']);
        $opcoes = Modulos::filtrarOpcoes([
            'view_lotes' => 'Lotes',
            'view_bairros' => 'Bairros',
            'gerenciar_mob_trechos' => 'Trechos',
            'view_auditoria' => 'Auditoria',
        ], $t);

        $this->assertSame(['view_bairros', 'gerenciar_mob_trechos', 'view_auditoria'], array_keys($opcoes));
    }

    public function test_permissoes_inativas_cobrem_modulos_desligados(): void
    {
        $inativas = Modulos::permissoesInativas($this->tenant(['base_cartografica', 'mob_infra']));
        $this->assertContains('view_lotes', $inativas);
        $this->assertContains('gerenciar_chamados', $inativas);
        $this->assertNotContains('view_bairros', $inativas);
        $this->assertNotContains('gerenciar_mob_vias', $inativas);
        $this->assertNotContains('view_users', $inativas);
    }

    public function test_camadas_artefatos_e_ferramentas(): void
    {
        $t = $this->tenant(['base_cartografica', 'mob_infra']);
        $this->assertSame('base_cartografica', Modulos::daCamada('bairros'));
        $this->assertSame('rural', Modulos::daCamada('rural-localidades')); // hífen do data-layer
        $this->assertSame('imobiliario', Modulos::daCamada('edificacoes'));
        $this->assertSame('base_cartografica', Modulos::daCamada('secoes_logradouro')); // D8
        $this->assertSame('chamados', Modulos::daCamada('chamados')); // D8
        $this->assertTrue(Modulos::camadaDisponivel('secoes_logradouro', $t));
        $this->assertFalse(Modulos::camadaDisponivel('chamados', $t));
        $this->assertTrue(Modulos::camadaDisponivel('mob_vias', $t));
        $this->assertFalse(Modulos::camadaDisponivel('lotes', $t));
        $this->assertTrue(Modulos::camadaDisponivel('camada_desconhecida', $t));
        $this->assertContains('lotes', Modulos::camadasIndisponiveis($t));
        $this->assertNotContains('bairros', Modulos::camadasIndisponiveis($t));

        $this->assertTrue(Modulos::artefatoDisponivel('bairro', $t));
        $this->assertFalse(Modulos::artefatoDisponivel('lote', $t));
        $this->assertTrue(Modulos::artefatoDisponivel('mob_via', $t));

        $this->assertTrue(Modulos::ferramentaDisponivel('medir', $t));
        $this->assertTrue(Modulos::ferramentaDisponivel('sentidos', $t));
        $this->assertFalse(Modulos::ferramentaDisponivel('numeracao', $t));
        $this->assertFalse(Modulos::ferramentaDisponivel('pgv_motor', $t));
    }

    public function test_requisitos_faltantes(): void
    {
        $this->assertSame([], Modulos::requisitosFaltantes(['base_cartografica', 'imobiliario', 'coleta_cadastral']));
        // coleta_cadastral exige imobiliario (presente); imobiliario exige base (ausente)
        $this->assertSame(
            ['imobiliario' => ['base_cartografica']],
            Modulos::requisitosFaltantes(['imobiliario', 'coleta_cadastral'])
        );
        $this->assertSame(
            ['coleta_cadastral' => ['imobiliario']],
            Modulos::requisitosFaltantes(['base_cartografica', 'coleta_cadastral'])
        );
        $this->assertSame(['pgv' => ['imobiliario']], Modulos::requisitosFaltantes(['pgv']));
        // D8: chamados é contratável sozinho (chamado = ponto + categoria, não depende de lote)
        $this->assertSame([], Modulos::requisitosFaltantes(['chamados']));
    }

    public function test_toda_permissao_do_seeder_tem_dono_conhecido(): void
    {
        // Toda permissão semeada deve pertencer a algum módulo ou ao núcleo — senão
        // ela aparece em qualquer prefeitura sem ninguém decidir isso.
        $arquivo = file_get_contents(base_path('database/seeders/PermissionsSeeder.php'));
        preg_match_all("/'([a-z_]+)'/", $arquivo, $m);
        $semDono = [];
        foreach (array_unique($m[1]) as $perm) {
            if (preg_match('/^(view|create|edit|delete|gerenciar|ver_camada|toolbar)_/', $perm) && Modulos::daPermissao($perm) === null) {
                $semDono[] = $perm;
            }
        }
        $this->assertSame([], $semDono, 'Permissões sem módulo no catálogo: '.implode(', ', $semDono));
    }
}
