<?php

namespace Database\Seeders;

use App\Models\CampoCustomizado;
use App\Models\MobTipoSinalizacao;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Módulo Mobilidade Urbana (docs/piuma.txt, Onda 0) — semeia por tenant com o
 * módulo `mob_infra` ativo (ou o slug de MOB_SEED_TENANT):
 *
 *  1. CATÁLOGO de tipos de sinalização (decisão 6.1) — nome + vertical/horizontal
 *     + cor por classe CTB (regulamentação vermelho, advertência amarelo,
 *     indicação verde, estacionamento azul, horizontal roxo). Ponto de partida:
 *     o município edita cor/ícone/nome à vontade.
 *  2. KIT de campos customizados do trecho viário (os ~18 atributos de
 *     calçada/estacionamento/vegetação do levantamento, com as listas
 *     observadas nos dados) + extras da rodovia DER-ES no mob_eixo.
 *
 * Idempotente (firstOrCreate). Uso:
 *   MOB_SEED_TENANT=<slug> php artisan db:seed --class=MobilidadeSeeder
 */
class MobilidadeSeeder extends Seeder
{
    /** [name, tipo, cor, codigo_ctb] */
    public const TIPOS = [
        // Vertical — regulamentação (vermelho)
        ['Parada Obrigatória (Pare)', 'vertical', '#DC2626', 'R-1'],
        ['Dê a Preferência', 'vertical', '#DC2626', 'R-2'],
        ['Sentido Proibido', 'vertical', '#DC2626', 'R-3'],
        ['Proibido Virar à Esquerda', 'vertical', '#DC2626', 'R-4a'],
        ['Proibido Virar à Direita', 'vertical', '#DC2626', 'R-4b'],
        ['Proibido Estacionar', 'vertical', '#DC2626', 'R-6a'],
        ['Proibido Parar e Estacionar', 'vertical', '#DC2626', 'R-6c'],
        ['Proibido Ultrapassar', 'vertical', '#DC2626', 'R-7'],
        ['Velocidade Máxima Permitida', 'vertical', '#DC2626', 'R-19'],
        ['Sentido de Circulação da Via', 'vertical', '#DC2626', 'R-24a'],
        ['Siga em Frente', 'vertical', '#DC2626', 'R-26'],
        ['Duplo Sentido de Circulação', 'vertical', '#DC2626', 'R-28'],
        ['Peso Bruto Total Limitado', 'vertical', '#DC2626', null],
        // Vertical — advertência (amarelo)
        ['Interseção em Círculo (Rotatória)', 'vertical', '#F59E0B', 'A-12'],
        ['Lombada à Frente', 'vertical', '#F59E0B', 'A-18'],
        ['Passagem Sinalizada de Pedestres', 'vertical', '#F59E0B', 'A-32b'],
        ['Trânsito Compartilhado (Ciclistas e Pedestres)', 'vertical', '#F59E0B', 'A-30c'],
        ['Curva Acentuada', 'vertical', '#F59E0B', null],
        ['Declive Acentuado', 'vertical', '#F59E0B', null],
        ['Animais Silvestres', 'vertical', '#F59E0B', null],
        ['Fiscalização Eletrônica', 'vertical', '#F59E0B', null],
        // Vertical — indicação (verde)
        ['Placa de Indicação', 'vertical', '#16A34A', null],
        ['Identificação de Ponto de Ônibus', 'vertical', '#16A34A', null],
        ['Ciclorrota / Ponto de Bicicleta', 'vertical', '#16A34A', null],
        // Vertical — estacionamento regulamentado (azul)
        ['Estacionamento Regulamentado', 'vertical', '#2563EB', 'R-6b'],
        ['Vaga de Idoso', 'vertical', '#2563EB', null],
        ['Vaga PCD', 'vertical', '#2563EB', null],
        ['Vaga de Moto', 'vertical', '#2563EB', null],
        ['Carga e Descarga', 'vertical', '#2563EB', null],
        ['Estacionamento de Curta Duração', 'vertical', '#2563EB', null],
        ['Veículos Oficiais', 'vertical', '#2563EB', null],
        ['Táxi', 'vertical', '#2563EB', null],
        ['Embarque e Desembarque', 'vertical', '#2563EB', null],
        // Horizontal (roxo)
        ['Faixa para Pedestres', 'horizontal', '#7C3AED', null],
        ['Travessia Elevada', 'horizontal', '#7C3AED', null],
        ['Lombada / Quebra-molas', 'horizontal', '#7C3AED', null],
        ['Ciclovia (pintura)', 'horizontal', '#7C3AED', null],
        // Fallback do de/para da importação (cinza)
        ['A Classificar', 'vertical', '#9CA3AF', null],
        ['A Classificar', 'horizontal', '#9CA3AF', null],
    ];

    /** Kit de campos customizados: [entidade, slug, label, tipo, opcoes] */
    public const KIT = [
        // mob_trecho — calçadas
        ['mob_trecho', 'estado_conservacao_calcada_esquerdo', 'Estado da Calçada (esquerda)', 'selecao', ['Ótimo', 'Bom', 'Regular', 'Ruim', 'Péssimo', 'Não possui']],
        ['mob_trecho', 'estado_conservacao_calcada_direito', 'Estado da Calçada (direita)', 'selecao', ['Ótimo', 'Bom', 'Regular', 'Ruim', 'Péssimo', 'Não possui']],
        ['mob_trecho', 'tipo_pavimentacao_calcada_esquerdo', 'Pavimentação da Calçada (esquerda)', 'selecao', ['Concreto', 'Piso drenante (intertravado)', 'Terra', 'Não possui']],
        ['mob_trecho', 'tipo_pavimentacao_calcada_direito', 'Pavimentação da Calçada (direita)', 'selecao', ['Concreto', 'Piso drenante (intertravado)', 'Terra', 'Não possui']],
        ['mob_trecho', 'medida_calcada_esquerdo', 'Largura da Calçada (esquerda)', 'selecao', ['Menos que 1,90m', 'Entre 1,90 e 2,35m', 'Maior que 2,35m', 'Não possui']],
        ['mob_trecho', 'medida_calcada_direito', 'Largura da Calçada (direita)', 'selecao', ['Menos que 1,90m', 'Entre 1,90 e 2,35m', 'Maior que 2,35m', 'Não possui']],
        ['mob_trecho', 'acessibilidade_calcadas', 'Acessibilidade das Calçadas', 'multipla', ['Rampa de Acessibilidade', 'Piso Tátil de Alerta', 'Piso Tátil Direcional', 'Não possui']],
        // mob_trecho — estacionamento
        ['mob_trecho', 'posicionamento_estacionamento', 'Posicionamento do Estacionamento', 'selecao', ['Lado Direito', 'Lado Esquerdo', 'Ambos os lados', 'Estacionamento no canteiro central', 'Não possui']],
        ['mob_trecho', 'tipologia_estacionamento_paralelo_via', 'Estacionamento Paralelo à Via', 'selecao', ['Na Direita', 'Na Esquerda', 'Ambos os lados', 'Não possui']],
        ['mob_trecho', 'tipologia_estacionamento_diagonal_via', 'Estacionamento Diagonal à Via', 'selecao', ['Na Direita', 'Na Esquerda', 'Ambos os lados', 'Não possui']],
        ['mob_trecho', 'tipologia_estacionamento_perpendicular_via', 'Estacionamento Perpendicular à Via', 'selecao', ['Na Direita', 'Na Esquerda', 'Ambos os lados', 'Não possui']],
        ['mob_trecho', 'tipo_de_vaga_estacionamento', 'Tipos de Vaga', 'multipla', ['Vaga Comum', 'Vaga de idoso', 'Vaga PCD (pessoa com deficiência)', 'Vaga rápida (embarque e desembarque)', 'Vaga rotativa', 'Vaga para motos', 'Proibido estacionar', 'Não possui']],
        ['mob_trecho', 'medida_estacionamento_direito', 'Largura do Estacionamento (direita)', 'selecao', ['Menor que 2,00m', 'Entre 2,10m e 2,50m', 'Maior que 2,5m', 'Não possui demarcação', 'Proibido estacionar']],
        ['mob_trecho', 'medida_estacionamento_esquerdo', 'Largura do Estacionamento (esquerda)', 'selecao', ['Menor que 2,00m', 'Entre 2,10m e 2,50m', 'Maior que 2,5m', 'Não possui demarcação', 'Proibido estacionar']],
        // mob_trecho — vegetação
        ['mob_trecho', 'tipo_vegetacao_existente', 'Vegetação Existente', 'selecao', ['Árvore', 'Arbusto', 'Vaso de plantas', 'Não possui']],
        ['mob_trecho', 'estado_conservacao_vegetacao', 'Estado da Vegetação', 'selecao', ['Ótimo', 'Bom', 'Regular', 'Ruim', 'Péssimo', 'Não possui']],
        ['mob_trecho', 'obstrucao_placas_vegetacao', 'Vegetação Obstrui Placas?', 'selecao', ['Sim', 'Não', 'Não possui']],
        ['mob_trecho', 'danificacao_calcada_vegetacao', 'Vegetação Danifica a Calçada?', 'selecao', ['Sim', 'Não', 'Não possui']],
        // mob_eixo — extras da rodovia DER-ES
        ['mob_eixo', 'sigla_rodovia', 'Sigla da Rodovia', 'texto', []],
        ['mob_eixo', 'sre', 'Código SRE (DER-ES)', 'texto', []],
        ['mob_eixo', 'situacao_der', 'Situação (DER-ES)', 'selecao', ['DUP', 'PAV', 'LEN', 'PLA', 'EOP']],
        ['mob_eixo', 'km_inicial', 'Km Inicial', 'numero', []],
        ['mob_eixo', 'km_final', 'Km Final', 'numero', []],
    ];

    public function run(): void
    {
        $slug = env('MOB_SEED_TENANT');

        $tenants = Tenant::query()
            ->when($slug, fn ($q) => $q->where('slug', $slug))
            ->get()
            ->filter(fn (Tenant $t) => $slug || in_array('mob_infra', $t->modules ?? []));

        if ($tenants->isEmpty()) {
            $this->command?->warn('Nenhum tenant com módulo mob_infra (ou MOB_SEED_TENANT não encontrado) — nada a semear.');

            return;
        }

        foreach ($tenants as $tenant) {
            $tipos = 0;
            foreach (self::TIPOS as $ordem => [$name, $tipo, $cor, $ctb]) {
                $criado = MobTipoSinalizacao::withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $name, 'tipo' => $tipo],
                    ['cor' => $cor, 'codigo_ctb' => $ctb, 'ordem' => $ordem, 'ativo' => true],
                );
                $criado->wasRecentlyCreated && $tipos++;
            }

            $campos = 0;
            foreach (self::KIT as $ordem => [$entidade, $campoSlug, $label, $tipo, $opcoes]) {
                $existe = CampoCustomizado::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('entidade', $entidade)
                    ->where('slug', $campoSlug)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($existe) {
                    continue;
                }

                CampoCustomizado::create([
                    'tenant_id' => $tenant->id,
                    'entidade' => $entidade,
                    'slug' => $campoSlug,
                    'label' => $label,
                    'tipo' => $tipo,
                    'opcoes' => $opcoes ?: null,
                    'obrigatorio' => false,
                    'na_coleta' => false,
                    'ordem' => $ordem,
                    'ativo' => true,
                ]);
                $campos++;
            }

            $this->command?->info("{$tenant->slug}: {$tipos} tipo(s) de sinalização + {$campos} campo(s) do kit criados.");
        }
    }
}
