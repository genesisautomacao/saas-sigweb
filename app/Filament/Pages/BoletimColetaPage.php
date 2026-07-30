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

/**
 * R67-3 — Boletim de Coleta: define o que o cadastrador de rua preenche no app.
 * Fonte ÚNICA da configuração do boletim — lista todos os campos disponíveis
 * (padrão do sistema + customizados do município) e marca quais vão para o app e
 * quais são obrigatórios. Criar campos e renomear rótulos fica em "Customizações".
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
        return auth()->user()?->can('gerenciar_campos_customizados') ?? false;
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $base = (array) data_get($tenant->data, 'coleta_campos_base', []);

        $estado = [
            // Campos SEM configuração própria (fotos, observação): controlados pelo tenant.data
            'base_lote' => (array) ($base['lote'] ?? []),
        ];

        // Campos padrão com domínio (aparecem/obrigatórios) — só entidades coletadas
        // no app (unidade fica fora: dados fiscais são somente-leitura em campo)
        foreach (CampoDominioService::PADROES as $entidade => $campos) {
            if (! in_array($entidade, CampoDominioService::ENTIDADES_NA_COLETA, true)) {
                continue;
            }
            foreach (array_keys($campos) as $campo) {
                $dominio = CampoDominio::where('entidade', $entidade)->where('campo', $campo)->first();

                $estado["col_{$entidade}_{$campo}"] = [
                    'na_coleta' => $dominio?->na_coleta ?? true,
                    'obrigatorio_coleta' => $dominio?->obrigatorio_coleta ?? false,
                ];
            }
        }

        // Campos customizados do município
        foreach (array_keys(CampoCustomizado::ENTIDADES) as $entidade) {
            foreach (CampoCustomizadoService::definicoes($entidade) as $campo) {
                $estado["cus_{$campo->id}"] = ['na_coleta' => (bool) $campo->na_coleta];
            }
        }

        $this->form->fill($estado);
    }

    public function form(Form $form): Form
    {
        $secoes = [];

        foreach (CampoCustomizado::ENTIDADES as $entidade => $rotuloEntidade) {
            $itens = [];

            // 1) Campos padrão do sistema (com rótulo já personalizado pelo município).
            // Unidade NÃO entra: seus campos padrão são fiscais (somente-leitura no app) —
            // no boletim ela só aparece com os campos customizados do município.
            $padroesColetaveis = in_array($entidade, CampoDominioService::ENTIDADES_NA_COLETA, true)
                ? array_keys(CampoDominioService::PADROES[$entidade] ?? [])
                : [];

            foreach ($padroesColetaveis as $campo) {
                if (! CampoDominioService::visivel($entidade, $campo)) {
                    continue; // município não usa este campo (Customizações → Campos Padrão)
                }

                $itens[] = Forms\Components\Fieldset::make(CampoDominioService::label($entidade, $campo))
                    ->schema([
                        Forms\Components\Toggle::make("col_{$entidade}_{$campo}.na_coleta")
                            ->label('Preencher no app')->default(true),
                        Forms\Components\Toggle::make("col_{$entidade}_{$campo}.obrigatorio_coleta")
                            ->label('Obrigatório')->default(false),
                    ])->columns(2);
            }

            // 2) Fotos e observação do lote (campos base sem domínio próprio)
            if ($entidade === 'lote') {
                $itens[] = Forms\Components\CheckboxList::make('base_lote')
                    ->label('Registros obrigatórios em campo')
                    ->options(array_diff_key(
                        ColetaConfigService::CAMPOS_BASE_LOTE,
                        CampoDominioService::PADROES['lote'] ?? []
                    ))
                    ->columns(2)
                    ->bulkToggleable()
                    ->columnSpanFull();
            }

            // 3) Campos customizados do município
            $customizados = CampoCustomizadoService::definicoes($entidade);
            foreach ($customizados as $campo) {
                $itens[] = Forms\Components\Fieldset::make($campo->label.' (campo do município)')
                    ->schema([
                        Forms\Components\Toggle::make("cus_{$campo->id}.na_coleta")
                            ->label('Preencher no app')->default(true),
                        Forms\Components\Placeholder::make("cus_{$campo->id}_info")
                            ->label('Obrigatório')
                            ->content($campo->obrigatorio ? 'Sim (definido no cadastro do campo)' : 'Não'),
                    ])->columns(2);
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

        // Fotos/observação exigidas (merge no data — map_lat/mobile_layers moram lá)
        $tenant->data = array_merge($tenant->data ?? [], [
            'coleta_campos_base' => [
                'lote' => array_values($dados['base_lote'] ?? []),
                'edificacao' => [],
            ],
        ]);
        $tenant->save();

        // Campos padrão: só as flags de coleta (rótulo/valores/visibilidade ficam em Customizações)
        foreach (CampoDominioService::PADROES as $entidade => $campos) {
            foreach (array_keys($campos) as $campo) {
                $estado = $dados["col_{$entidade}_{$campo}"] ?? null;

                if ($estado === null) {
                    continue; // campo oculto pelo município — não altera
                }

                $atual = CampoDominio::where('entidade', $entidade)->where('campo', $campo)->first();

                CampoDominio::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'entidade' => $entidade, 'campo' => $campo],
                    [
                        'label' => $atual?->label,
                        'opcoes' => $atual?->opcoes,
                        'visivel' => $atual?->visivel ?? true,
                        'na_coleta' => (bool) ($estado['na_coleta'] ?? true),
                        'obrigatorio_coleta' => (bool) ($estado['obrigatorio_coleta'] ?? false),
                    ]
                );
            }
        }

        // Campos customizados: flag "aparece no app"
        foreach (array_keys(CampoCustomizado::ENTIDADES) as $entidade) {
            foreach (CampoCustomizadoService::definicoes($entidade) as $campo) {
                $estado = $dados["cus_{$campo->id}"] ?? null;

                if ($estado !== null) {
                    CampoCustomizado::whereKey($campo->id)->update(['na_coleta' => (bool) ($estado['na_coleta'] ?? true)]);
                }
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
