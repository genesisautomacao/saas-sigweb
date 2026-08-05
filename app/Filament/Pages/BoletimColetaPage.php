<?php

namespace App\Filament\Pages;

use App\Models\CampoCustomizado;
use App\Models\CampoDominio;
use App\Services\Coleta\CampoCustomizadoService;
use App\Services\Coleta\CampoDominioService;
use App\Services\Coleta\ColetaConfigService;
use App\Traits\HasTenantModule;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;

/**
 * R67-3 — Boletim de Coleta: define o que o cadastrador de rua vê e preenche no app.
 *
 * Spec final (usuário, 2026-08-04): TODOS os campos de lote, edificação e unidade
 * aparecem aqui, cada um com o modo de uso — "Não usar" / "Apenas leitura" /
 * "Preencher em campo" — e, quando Preencher, o toggle de Obrigatório (que é
 * INDEPENDENTE da obrigatoriedade do sistema web). Dados vindos do cadastro
 * oficial/tributário (proprietário atual, fiscais, área calculada) não oferecem
 * "Preencher": divergência entra pelos campos customizados do município.
 *
 * Fonte ÚNICA da configuração do boletim. Criar campos e renomear rótulos fica
 * em "Customizações".
 */
class BoletimColetaPage extends Page implements HasForms
{
    use HasTenantModule, InteractsWithForms;

    protected static ?string $tenantModule = 'imobiliario';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Boletim de Coleta';

    protected static ?string $title = 'Boletim de Coleta (app do cadastrador)';

    protected static ?string $navigationGroup = 'Coleta cadastral';

    protected static ?int $navigationSort = 33;

    protected static string $view = 'filament.pages.boletim-coleta';

    protected static ?string $slug = 'boletim-coleta';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->temPermissao('gerenciar_campos_customizados') ?? false;
    }

    /** uso = 'nao' | 'leitura' | 'preencher' a partir das duas flags. */
    protected static function usoDeFlags(bool $naColeta, bool $leitura): string
    {
        return ! $naColeta ? 'nao' : ($leitura ? 'leitura' : 'preencher');
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $estado = [];

        // 1) Campos padrão com domínio (ex.: lote.ocupacao)
        foreach (CampoDominioService::PADROES as $entidade => $campos) {
            if (! in_array($entidade, CampoDominioService::ENTIDADES_NA_COLETA, true)) {
                continue;
            }
            foreach (array_keys($campos) as $campo) {
                $dominio = CampoDominio::where('entidade', $entidade)->where('campo', $campo)->first();

                $estado["col_{$entidade}_{$campo}"] = [
                    'uso' => self::usoDeFlags($dominio?->na_coleta ?? true, $dominio?->leitura_coleta ?? false),
                    'obrigatorio' => (bool) ($dominio?->obrigatorio_coleta ?? false),
                ];
            }
        }

        // 2) Campos base (fotos/observação do lote, área construída da edificação)
        foreach (ColetaConfigService::baseConfig($tenant) as $entidade => $campos) {
            foreach ($campos as $campo => $cfg) {
                $estado["base_{$entidade}_{$campo}"] = [
                    'uso' => self::usoDeFlags($cfg['na_coleta'], $cfg['leitura']),
                    'obrigatorio' => $cfg['obrigatorio'],
                ];
            }
        }

        // 3) Dados somente-leitura (área/testada do lote; cadastro+fiscais da unidade)
        foreach (array_keys(ColetaConfigService::CAMPOS_LEITURA) as $entidade) {
            $selecionados = array_column(ColetaConfigService::leitura($entidade, $tenant), 'campo');

            foreach (array_keys(ColetaConfigService::opcoesLeitura($entidade, $tenant->id)) as $campo) {
                $estado["leit_{$entidade}_{$campo}"] = [
                    'uso' => in_array($campo, $selecionados, true) ? 'leitura' : 'nao',
                ];
            }
        }

        // 4) Campos customizados do município (as 3 entidades coletáveis)
        foreach (CampoCustomizado::ENTIDADES_COLETAVEIS as $entidade) {
            foreach (CampoCustomizadoService::definicoes($entidade) as $campo) {
                $estado["cus_{$campo->id}"] = [
                    'uso' => self::usoDeFlags((bool) $campo->na_coleta, (bool) $campo->leitura_coleta),
                    'obrigatorio' => (bool) $campo->obrigatorio_coleta,
                ];
            }
        }

        $this->form->fill($estado);
    }

    /**
     * Fieldset padrão de um item do boletim: Select do modo de uso + Obrigatório
     * (visível só no modo Preencher). $permitePreencher=false = dado do cadastro
     * oficial (só Não mostrar / Apenas leitura).
     */
    protected function controle(string $path, string $rotulo, bool $permitePreencher, ?string $nota = null): Forms\Components\Fieldset
    {
        $opcoes = $permitePreencher
            ? ['nao' => 'Não usar no app', 'leitura' => 'Apenas leitura', 'preencher' => 'Preencher em campo']
            : ['nao' => 'Não mostrar no app', 'leitura' => 'Apenas leitura'];

        $componentes = [
            Forms\Components\Select::make("{$path}.uso")
                ->label('Uso no app')
                ->options($opcoes)
                ->selectablePlaceholder(false)
                ->live(),
        ];

        if ($permitePreencher) {
            $componentes[] = Forms\Components\Toggle::make("{$path}.obrigatorio")
                ->label('Obrigatório')
                ->helperText($nota)
                ->visible(fn (Forms\Get $get) => $get("{$path}.uso") === 'preencher')
                ->default(false);
        }

        return Forms\Components\Fieldset::make($rotulo)->schema($componentes)->columns(2);
    }

    public function form(Form $form): Form
    {
        $tenant = Filament::getTenant();
        $secoes = [];

        foreach (Arr::only(CampoCustomizado::ENTIDADES, CampoCustomizado::ENTIDADES_COLETAVEIS) as $entidade => $rotuloEntidade) {
            $itens = [];

            // 1) Campos padrão do sistema (rótulo já personalizado pelo município)
            $padroesColetaveis = in_array($entidade, CampoDominioService::ENTIDADES_NA_COLETA, true)
                ? array_keys(CampoDominioService::PADROES[$entidade] ?? [])
                : [];

            foreach ($padroesColetaveis as $campo) {
                if (! CampoDominioService::visivel($entidade, $campo)) {
                    continue; // município não usa este campo (Customizações → Campos Padrão)
                }

                $itens[] = $this->controle(
                    "col_{$entidade}_{$campo}",
                    CampoDominioService::label($entidade, $campo).' (campo padrão)',
                    permitePreencher: true
                );
            }

            // 2) Campos base da entidade (fotos, observação, área construída)
            $basesEntidade = match ($entidade) {
                'lote' => ColetaConfigService::CAMPOS_BASE_LOTE,
                'edificacao' => ColetaConfigService::CAMPOS_BASE_EDIFICACAO,
                default => [],
            };

            foreach ($basesEntidade as $campo => $rotuloCampo) {
                if (isset(CampoDominioService::PADROES[$entidade][$campo])) {
                    continue; // ex.: lote.ocupacao — já listado como campo padrão acima
                }

                $itens[] = $this->controle("base_{$entidade}_{$campo}", $rotuloCampo, permitePreencher: true);
            }

            // 3) Dados do cadastro oficial/tributário — SOMENTE leitura ou ocultos
            // (divergência é apontada nos campos customizados, nunca aqui)
            foreach (ColetaConfigService::opcoesLeitura($entidade, $tenant?->id) as $campo => $rotuloCampo) {
                $itens[] = $this->controle(
                    "leit_{$entidade}_{$campo}",
                    $rotuloCampo.' (dado do cadastro)',
                    permitePreencher: false
                );
            }

            // 4) Campos customizados do município — Obrigatório aqui é o DO BOLETIM,
            // independente do `obrigatorio` do formulário web
            foreach (CampoCustomizadoService::definicoes($entidade) as $campo) {
                $itens[] = $this->controle(
                    "cus_{$campo->id}",
                    $campo->label.' (campo do município)',
                    permitePreencher: true,
                    nota: $campo->obrigatorio ? 'No sistema web: obrigatório' : 'No sistema web: opcional'
                );
            }

            if (empty($itens)) {
                continue;
            }

            $secoes[] = Forms\Components\Section::make($rotuloEntidade)
                ->schema($itens)
                ->collapsible();
        }

        return $form->schema($secoes)->statePath('data');
    }

    public function salvar(): void
    {
        $dados = $this->form->getState();
        $tenant = Filament::getTenant();

        $uso = fn (?array $estado): string => in_array($estado['uso'] ?? null, ['nao', 'leitura', 'preencher'], true)
            ? $estado['uso']
            : 'preencher';

        // 1) Campos padrão: só as flags de coleta (rótulo/valores/visibilidade ficam em Customizações)
        foreach (CampoDominioService::PADROES as $entidade => $campos) {
            foreach (array_keys($campos) as $campo) {
                $estado = $dados["col_{$entidade}_{$campo}"] ?? null;

                if ($estado === null) {
                    continue; // campo oculto pelo município — não altera
                }

                $modo = $uso($estado);
                $atual = CampoDominio::where('entidade', $entidade)->where('campo', $campo)->first();

                CampoDominio::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'entidade' => $entidade, 'campo' => $campo],
                    [
                        'label' => $atual?->label,
                        'opcoes' => $atual?->opcoes,
                        'visivel' => $atual?->visivel ?? true,
                        'na_coleta' => $modo !== 'nao',
                        'leitura_coleta' => $modo === 'leitura',
                        'obrigatorio_coleta' => $modo === 'preencher' && (bool) ($estado['obrigatorio'] ?? false),
                    ]
                );
            }
        }

        // 2) Campos base: flags novas + lista legada derivada (app publicado:
        // exigido = visível E preenchível E obrigatório)
        $baseCfg = [];
        $legado = ['lote' => [], 'edificacao' => []];

        foreach (['lote' => ColetaConfigService::CAMPOS_BASE_LOTE, 'edificacao' => ColetaConfigService::CAMPOS_BASE_EDIFICACAO] as $entidade => $campos) {
            foreach (array_keys($campos) as $campo) {
                $estado = $dados["base_{$entidade}_{$campo}"] ?? null;

                if ($estado === null) {
                    continue;
                }

                $modo = $uso($estado);
                $obrigatorio = $modo === 'preencher' && (bool) ($estado['obrigatorio'] ?? false);

                $baseCfg[$entidade][$campo] = [
                    'na_coleta' => $modo !== 'nao',
                    'leitura' => $modo === 'leitura',
                    'obrigatorio' => $obrigatorio,
                ];

                if ($obrigatorio) {
                    $legado[$entidade][] = $campo;
                }
            }
        }

        // 3) Dados somente-leitura por entidade
        $leituraSelecao = [];

        foreach (array_keys(ColetaConfigService::CAMPOS_LEITURA) as $entidade) {
            $leituraSelecao[$entidade] = [];

            foreach (array_keys(ColetaConfigService::opcoesLeitura($entidade, $tenant->id)) as $campo) {
                if ($uso($dados["leit_{$entidade}_{$campo}"] ?? ['uso' => 'nao']) === 'leitura') {
                    $leituraSelecao[$entidade][] = $campo;
                }
            }
        }

        $tenant->data = array_merge($tenant->data ?? [], [
            'coleta_campos_base_config' => $baseCfg,
            'coleta_campos_base' => $legado,
            'coleta_leitura' => $leituraSelecao,
        ]);
        $tenant->save();

        // 4) Campos customizados
        foreach (CampoCustomizado::ENTIDADES_COLETAVEIS as $entidade) {
            foreach (CampoCustomizadoService::definicoes($entidade) as $campo) {
                $estado = $dados["cus_{$campo->id}"] ?? null;

                if ($estado === null) {
                    continue;
                }

                $modo = $uso($estado);

                CampoCustomizado::whereKey($campo->id)->update([
                    'na_coleta' => $modo !== 'nao',
                    'leitura_coleta' => $modo === 'leitura',
                    'obrigatorio_coleta' => $modo === 'preencher' && (bool) ($estado['obrigatorio'] ?? false),
                ]);
            }
        }

        CampoDominioService::limparCache();
        CampoCustomizadoService::limparCache();

        Notification::make()
            ->success()
            ->title('Boletim atualizado')
            ->body('O app de coleta já usa esta configuração na próxima sincronização.')
            ->send();
    }
}
